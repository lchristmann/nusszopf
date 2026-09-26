#!/bin/sh
# P-16: the upgrade between two published releases through the real download path. The previous release is installed
# from its own published compose file and template (the installer of 1.0.0-rc.1 cannot download them, P16-04, so this
# install uses the new installer with the old release's files), then `install.sh --upgrade <to>` runs as an operator
# runs it: downloaded from releases/download/<to>/, which fetches that release's files, and Compose pulls the images
# from GHCR. A marker written before the upgrade must be there afterwards. Nothing is built or faked.
#
#   sh scripts/release-upgrade-check.sh <from-tag> <to-tag>     e.g. 1.0.0-rc.1 1.0.0-rc.2
#
# Environment: RELEASE_UPGRADE_PORT (default 18131). Needs Docker with the Compose plugin, curl and openssl.
set -eu

FROM="${1:?Usage: sh scripts/release-upgrade-check.sh <from-tag> <to-tag>}"
TO="${2:?Usage: sh scripts/release-upgrade-check.sh <from-tag> <to-tag>}"
PORT="${RELEASE_UPGRADE_PORT:-18131}"
BASE="http://127.0.0.1:$PORT"
DL="https://github.com/lchristmann/nusszopf/releases/download"
WORK="$(mktemp -d)"
export COMPOSE_PROJECT_NAME="nusszopf-release-upgrade"

cleanup() {
    status=$?
    (cd "$WORK/host" 2>/dev/null && docker compose down -v --remove-orphans >/dev/null 2>&1) || true
    rm -rf "$WORK"
    [ "$status" -eq 0 ] && echo "RELEASE UPGRADE CHECK PASSED ($FROM -> $TO, $(uname -m))" || echo "RELEASE UPGRADE CHECK FAILED ($FROM -> $TO, $(uname -m))"
    exit "$status"
}
trap cleanup EXIT

step() { printf '\n== %s\n' "$1"; }
fail() { echo "FAIL: $1" >&2; (cd "$WORK/host" && docker compose ps 2>&1; docker compose logs --tail 25 php-fpm 2>&1) | tail -40 || true; exit 1; }
healthy() {
    i=0
    until docker compose exec -T php-fpm php artisan nusszopf:health > health.out 2>&1 && ! grep -q FAILED health.out; do
        i=$((i + 1)); [ "$i" -le 40 ] || { cat health.out; fail "nusszopf:health did not turn healthy"; }
        sleep 5
    done
    grep -q "Nusszopf $1" health.out || fail "the installation does not report $1"
}

step "The previous release's published files ($FROM)"
mkdir -p "$WORK/from" "$WORK/host"
curl -fsSL "$DL/$FROM/docker-compose.yaml" -o "$WORK/from/docker-compose.yaml" || fail "no docker-compose.yaml in $FROM"
# The template is attached without a dot from 1.0.0-rc.2 on; GitHub served the first release's under another name.
curl -fsSL "$DL/$FROM/env.production.example" -o "$WORK/from/env.production.example" 2>/dev/null \
    || curl -fsSL "$DL/$FROM/default.env.production.example" -o "$WORK/from/env.production.example" \
    || fail "no template in $FROM"

step "Install $FROM and start it"
curl -fsSL "$DL/$TO/install.sh" -o "$WORK/install-new.sh" || fail "install.sh is not served under releases/download/$TO/"
cd "$WORK/host"
NUSSZOPF_BASE_URL="file://$WORK/from" sh "$WORK/install-new.sh" "$BASE" "$FROM" >/dev/null || fail "install failed"
sed -i "s|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=release-check@example.test|; s|^APP_PORT=.*|APP_PORT=$PORT|" .env
docker compose up -d --wait --wait-timeout 420 >/dev/null 2>&1 || fail "$FROM did not become healthy"
healthy "$FROM"
docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "create table p16_marker as select 42 as answer"' >/dev/null

step "Upgrade to $TO with the downloaded install.sh --upgrade $TO"
sh "$WORK/install-new.sh" --upgrade "$TO" > "$WORK/upgrade.out" || { cat "$WORK/upgrade.out"; fail "install.sh --upgrade failed"; }
grep -q "^NUSSZOPF_VERSION=$TO\$" .env || fail ".env does not name $TO"
started="$(date +%s)"
docker compose pull --quiet || fail "docker compose pull failed"
docker compose up -d --wait --wait-timeout 420 >/dev/null 2>&1 || fail "$TO did not become healthy"
healthy "$TO"
echo "pulled and healthy $(($(date +%s) - started)) s after the upgrade"
cmp -s docker-compose.yaml "$WORK/from/docker-compose.yaml" && echo "note: the compose file is unchanged between the releases" || echo "the compose file was replaced by $TO's"
[ "$(docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select answer from p16_marker"' | tr -d '\r')" = "42" ] || fail "the data written before the upgrade is gone"
for path in /up /login /search; do
    [ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE$path")" = "200" ] || fail "$path does not answer 200"
done
[ -f docker-compose.yaml.previous ] && [ -f .env.previous ] || fail "the upgrade kept no .previous files (the rollback path of operations.md)"
