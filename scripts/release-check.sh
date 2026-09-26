#!/bin/sh
# P-16: installs a published release the way an operator does, from what GitHub and GHCR serve, and checks that it
# runs. Nothing is built or faked: install.sh is downloaded from the release, it downloads the release's compose file
# and template, and Compose pulls the images from GHCR.
#
#   sh scripts/release-check.sh <tag>          e.g. sh scripts/release-check.sh 1.0.0-rc.2
#
# Environment: RELEASE_CHECK_PORT (default 18130), RELEASE_CHECK_KEEP=1 to leave the stack running afterwards.
# Needs Docker with the Compose plugin, curl and openssl (the documented prerequisites). The one setting it must
# make is the sender address; mail is not sent here (scripts/mail-delivery-test.sh does that).
#
# For a hands-on check with real devices (docs/release/parity/P-16-release.md, "Real devices"):
#   RELEASE_CHECK_KEEP=1 RELEASE_CHECK_DOUBLES=1 RELEASE_CHECK_URL=http://<this machine's LAN address>:18130 \
#       sh scripts/release-check.sh <tag>
# adds Mailpit as the mail relay (its inbox is on RELEASE_CHECK_PORT + 1) and the LocationIQ stub, so registration,
# contact mails and the place suggestions work without any account. Never for a real installation.
set -eu

TAG="${1:?Usage: sh scripts/release-check.sh <tag>}"
PORT="${RELEASE_CHECK_PORT:-18130}"
BASE="${RELEASE_CHECK_URL:-http://127.0.0.1:$PORT}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
export COMPOSE_PROJECT_NAME="nusszopf-release-check"

cleanup() {
    status=$?
    if [ "${RELEASE_CHECK_KEEP:-0}" != "1" ]; then
        (cd "$WORK" && docker compose down -v --remove-orphans >/dev/null 2>&1) || true
        rm -rf "$WORK"
    else
        echo "Stack left running in $WORK (project $COMPOSE_PROJECT_NAME)."
    fi
    [ "$status" -eq 0 ] && echo "RELEASE CHECK PASSED ($TAG, $(uname -m))" || echo "RELEASE CHECK FAILED ($TAG, $(uname -m))"
    exit "$status"
}
trap cleanup EXIT

step() { printf '\n== %s\n' "$1"; }
fail() { echo "FAIL: $1" >&2; (cd "$WORK" && docker compose ps 2>&1; docker compose logs --tail 30 php-fpm 2>&1) | tail -50 || true; exit 1; }
status_of() { curl -s -o /dev/null -w '%{http_code}' "$BASE$1"; }

step "Download install.sh from the release"
cd "$WORK"
curl -fsSLO "https://github.com/lchristmann/nusszopf/releases/download/$TAG/install.sh" || fail "install.sh is not served under releases/download/$TAG/"
for asset in docker-compose.yaml env.production.example; do
    [ "$(curl -s -o /dev/null -w '%{http_code}' -L "https://github.com/lchristmann/nusszopf/releases/download/$TAG/$asset")" = "200" ] \
        || fail "the release does not serve $asset"
done

step "Install with install.sh, without NUSSZOPF_BASE_URL"
sh install.sh "$BASE" "$TAG" >/dev/null || fail "install.sh failed"
grep -q "^NUSSZOPF_VERSION=$TAG\$" .env || fail ".env does not name $TAG"
sed -i "s|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=release-check@example.test|; s|^APP_PORT=.*|APP_PORT=$PORT|" .env
grep -q "^APP_PORT=$PORT\$" .env || echo "APP_PORT=$PORT" >> .env

if [ "${RELEASE_CHECK_DOUBLES:-0}" = "1" ]; then
    step "Test doubles for a hands-on check: Mailpit and the LocationIQ stub (compose.override.yaml)"
    cp "$ROOT/docker/stubs/locationiq.conf" locationiq.conf
    cat > compose.override.yaml <<YAML
services:
  mailpit:
    image: axllent/mailpit:latest
    ports:
      - "0.0.0.0:$((PORT + 1)):8025"
  locationiq-stub:
    image: nginx:alpine
    volumes:
      - ./locationiq.conf:/etc/nginx/conf.d/default.conf:ro
YAML
    sed -i "s|^MAIL_HOST=.*|MAIL_HOST=mailpit|; s|^MAIL_PORT=.*|MAIL_PORT=1025|; s|^LOCATIONIQ_KEY=.*|LOCATIONIQ_KEY=nusszopf-e2e-stub-key|; s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=false|" .env
    printf '\n# Test-only (scripts/release-check.sh, RELEASE_CHECK_DOUBLES=1)\nLOCATIONIQ_URL=http://locationiq-stub/v1/autocomplete.php\n' >> .env
    echo "Mail inbox: ${BASE%:*}:$((PORT + 1))"
fi

step "Pull the images from GHCR and start (timed)"
started="$(date +%s)"
docker compose pull --quiet || fail "docker compose pull failed"
pulled="$(date +%s)"
docker compose up -d --wait --wait-timeout 420 >/dev/null 2>&1 || fail "the stack did not become healthy"
echo "pull: $((pulled - started)) s, start to healthy: $(($(date +%s) - pulled)) s, total: $(($(date +%s) - started)) s"

step "The release runs"
echo "host $(uname -m); images: $(docker image inspect --format '{{.Architecture}}' "ghcr.io/lchristmann/nusszopf-php-fpm:$TAG" "ghcr.io/lchristmann/nusszopf-web:$TAG" | tr '\n' ' ')"
# The queue worker and the scheduler prove themselves with a heartbeat per minute (docs/deployment/README.md).
i=0
until docker compose exec -T php-fpm php artisan nusszopf:health > health.out 2>&1 && ! grep -q "FAILED" health.out; do
    i=$((i + 1)); [ "$i" -le 40 ] || { cat health.out; fail "nusszopf:health did not turn healthy within 200 s"; }
    sleep 5
done
cat health.out
grep -q "Nusszopf $TAG" health.out || fail "the installation does not report $TAG"
for path in /up /login /search /legalNotice; do
    [ "$(status_of $path)" = "200" ] || fail "$path answers $(status_of $path)"
done
[ "$(status_of /diese-seite-gibt-es-nicht)" = "404" ] || fail "an unknown page is not a 404"
curl -sI "$BASE/login" | grep -qi '^content-security-policy:' || fail "no Content-Security-Policy header"
docker compose exec -T php-fpm php artisan schedule:list | grep -q 'newsletter:purge-unconfirmed' || fail "the scheduler has no newsletter purge"
