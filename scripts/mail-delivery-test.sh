#!/bin/sh
# P-13 (docs/release/parity/P-13-email-delivery.md): every mail type through a real relay, on the production images.
#
#   P13_RECIPIENT=you@example.org sh scripts/mail-delivery-test.sh            # install, start, send, report
#   P13_RECIPIENT=you@example.org P13_KEEP=1 sh scripts/mail-delivery-test.sh  # leave the stack running
#
# 1. Builds the production images from this working copy and installs the operator's stack into a clean directory
#    with install.sh, as prod-e2e.sh does, but with no Mailpit: the mail goes out through the relay you configure.
# 2. Copies MAIL_MAILER, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, RESEND_API_KEY (or MAIL_HOST/PORT/USERNAME/PASSWORD/SCHEME)
#    from the file P13_ENV_FILE (default: this working copy's untracked .env) into the installed .env. The values are
#    never printed.
# 3. Queues one mail of every type (scripts/mail-delivery-send.php) to P13_RECIPIENT and waits until the production
#    queue worker has delivered them all: nothing waiting, nothing in failed_jobs.
#
# Environment: P13_RECIPIENT (required: a mailbox you can read; it is never a default), P13_ENV_FILE, P13_PORT (default
# 18113), P13_KEEP=1. Needs Docker, curl and openssl. It sends real mail: seven messages per run.
set -eu

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
: "${P13_RECIPIENT:?Set P13_RECIPIENT to the mailbox that receives the test mails}"
ENV_FILE="${P13_ENV_FILE:-$ROOT/.env}"
PORT="${P13_PORT:-18113}"
VERSION="mail-delivery"
PROJECT="nusszopf-mail-delivery"
WORK="$(mktemp -d)"
BASE="http://127.0.0.1:$PORT"

compose() {
    NUSSZOPF_BUILD_CONTEXT="$ROOT" docker compose -p "$PROJECT" -f docker-compose.yaml -f "$ROOT/compose.prod.yaml" "$@"
}

cleanup() {
    status=$?
    if [ "${P13_KEEP:-0}" != "1" ]; then
        (cd "$WORK" && compose down -v --remove-orphans >/dev/null 2>&1) || true
        rm -rf "$WORK"
    else
        echo "Stack left running in $WORK (project $PROJECT)."
    fi
    [ "$status" -eq 0 ] && echo "MAIL DELIVERY TEST PASSED" || echo "MAIL DELIVERY TEST FAILED"
    exit "$status"
}
trap cleanup EXIT

step() { printf '\n== %s\n' "$1"; }
fail() { echo "FAIL: $1" >&2; exit 1; }

step "Install into a clean directory (install.sh, release assets taken from this working copy)"
mkdir "$WORK/assets"
cp "$ROOT/docker-compose.yaml" "$WORK/assets/docker-compose.yaml"
sed "s|^NUSSZOPF_VERSION=.*|NUSSZOPF_VERSION=$VERSION|" "$ROOT/.env.production.example" > "$WORK/assets/env.production.example"
cd "$WORK"
NUSSZOPF_BASE_URL="file://$WORK/assets" sh "$ROOT/scripts/install.sh" "$BASE" >/dev/null

set_value() {
    if grep -q "^$1=" .env; then
        # A value read from another file may contain sed metacharacters: pass it through the environment.
        VALUE="$2" KEY="$1" awk 'BEGIN { k = ENVIRON["KEY"] "="; v = ENVIRON["VALUE"] } index($0, k) == 1 { print k v; next } { print }' .env > .env.tmp
        mv .env.tmp .env
    else
        printf '%s=%s\n' "$1" "$2" >> .env
    fi
}
from_env_file() { grep -m1 "^$1=" "$ENV_FILE" | cut -d= -f2- || true; }

step "Configure the relay from $ENV_FILE (values are not printed)"
set_value APP_PORT "$PORT"
mailer="$(from_env_file MAIL_MAILER)"
[ -n "$mailer" ] || fail "MAIL_MAILER is not set in $ENV_FILE"
[ "$mailer" != "log" ] && [ "$mailer" != "array" ] || fail "MAIL_MAILER=$mailer delivers nothing; configure a real relay"
keys="MAIL_MAILER MAIL_FROM_ADDRESS MAIL_FROM_NAME"
if [ "$mailer" = "resend" ]; then keys="$keys RESEND_API_KEY"; else keys="$keys MAIL_SCHEME MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD"; fi
for key in $keys; do
    value="$(from_env_file "$key")"
    [ -z "$value" ] || set_value "$key" "$value"
done
[ "$mailer" = "resend" ] && ! grep -q '^RESEND_API_KEY=.\+' .env && fail "RESEND_API_KEY is empty in $ENV_FILE"
[ "$mailer" != "smtp" ] || ! grep -q '^MAIL_HOST=mailpit$' .env || fail "MAIL_HOST=mailpit is the development catcher, not a relay"
echo "mailer: $mailer, from: $(grep '^MAIL_FROM_ADDRESS=' .env | cut -d= -f2-)"

step "Build the images and start the stack (waits for every healthcheck)"
compose up -d --build --wait --wait-timeout 420

step "Queue one mail of every type to the recipient"
P13_RECIPIENT="$P13_RECIPIENT" ; export P13_RECIPIENT
compose exec -T -e P13_RECIPIENT="$P13_RECIPIENT" php-fpm php < "$ROOT/scripts/mail-delivery-send.php"

step "Wait for the production queue worker to deliver them all"
i=0
while :; do
    waiting="$(compose exec -T redis redis-cli llen queues:default | tr -d '\r')"
    reserved="$(compose exec -T redis redis-cli zcard queues:default:reserved | tr -d '\r')"
    delayed="$(compose exec -T redis redis-cli zcard queues:default:delayed | tr -d '\r')"
    [ "$waiting$reserved$delayed" = "000" ] && break
    i=$((i + 1)); [ "$i" -le 240 ] || fail "the queue did not drain in 4 minutes (waiting $waiting, reserved $reserved, delayed $delayed)"
    sleep 1
done
failed="$(compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select count(*) from failed_jobs"' | tr -d '\r')"
echo "queue drained; failed_jobs: $failed"
[ "$failed" = "0" ] || fail "$failed job(s) in failed_jobs"
compose logs --no-color queue-worker 2>&1 | grep -E "DONE|FAIL" | sed -E 's/^[^|]*\| //' | tail -20
