#!/bin/sh
# P-9 (docs/release/parity/P-09-upgrade.md): the upgrade test. Builds the release of <from-ref> and installs it the
# way an operator does (its own install.sh and docker-compose.yaml), fills it with a representative dataset, leaves
# work queued and a browser signed in, then upgrades it to this working copy with the documented procedure
# (docs/deployment/operations.md, "Upgrades") and checks that nothing was lost and that everything still works.
#
#   sh scripts/upgrade-test.sh <from-ref> [--suite]
#
#   <from-ref>  the previous release's tag; any commit from 8c4a2eb (the first operator stack) on works
#   --suite     afterwards also run the whole Playwright suite (desktop browsers) against the upgraded installation
#
# Environment: UPGRADE_PORT (default 18093), UPGRADE_KEEP=1 to leave the stack running afterwards.
# Needs Docker, git, curl, openssl and python3. The test doubles (Mailpit, the LocationIQ stub) are those of
# scripts/prod-e2e.sh. The images are built locally, so `docker compose pull` fetches only the third-party images.
set -eu

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FROM="${1:?Usage: sh scripts/upgrade-test.sh <from-ref> [--suite]}"
SUITE=0
[ "${2:-}" = "--suite" ] && SUITE=1
# RELEASE_TAG=<tag> (P-16): the working copy is a checkout of that release, and both releases are pulled from GHCR
# under their own tags, not built: <from-ref> must then be a published tag.
OLD="upgrade-from"
NEW="upgrade-to"
if [ -n "${RELEASE_TAG:-}" ]; then OLD="$FROM"; NEW="$RELEASE_TAG"; fi
PORT="${UPGRADE_PORT:-18093}"
BASE="http://127.0.0.1:$PORT"
WORK="$(mktemp -d)"
PLAYWRIGHT_IMAGE="mcr.microsoft.com/playwright:v1.63.0-noble"

export COMPOSE_PROJECT_NAME="nusszopf-upgrade"
export COMPOSE_FILE="docker-compose.yaml:$ROOT/tests/E2E/production/compose.e2e.yaml"
export NUSSZOPF_BUILD_CONTEXT="$ROOT"

cleanup() {
    status=$?
    if [ "${UPGRADE_KEEP:-0}" != "1" ]; then
        (cd "$WORK/host" 2>/dev/null && docker compose down -v --remove-orphans >/dev/null 2>&1) || true
        git -C "$ROOT" worktree remove --force "$WORK/from" >/dev/null 2>&1 || true
        rm -rf "$WORK"
    else
        echo "Stack left running in $WORK/host (project $COMPOSE_PROJECT_NAME)."
    fi
    [ "$status" -eq 0 ] && echo "UPGRADE TEST PASSED" || echo "UPGRADE TEST FAILED"
    exit "$status"
}
trap cleanup EXIT

step() { printf '\n== %s\n' "$1"; }
fail() { echo "FAIL: $1" >&2; (cd "$WORK/host" && docker compose logs --tail 40 php-fpm queue-worker 2>&1 | tail -60) || true; exit 1; }
set_value() { sed -i "s|^$1=.*|$1=$2|" .env; }
q() { docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "$0"' "$1"; }

# build <tree> <version>: builds that tree's images, or, with RELEASE_TAG set (P-16), pulls the published ones.
build() {
    if [ -n "${RELEASE_TAG:-}" ]; then
        docker pull -q "ghcr.io/lchristmann/nusszopf-php-fpm:$2" >/dev/null
        docker pull -q "ghcr.io/lchristmann/nusszopf-web:$2" >/dev/null
        return
    fi
    docker build -q -f "$1/docker/php/Dockerfile" --target php-fpm --build-arg NUSSZOPF_VERSION="$2" \
        -t "ghcr.io/lchristmann/nusszopf-php-fpm:$2" "$1" >/dev/null
    docker build -q -f "$1/docker/php/Dockerfile" --target nginx --build-arg NUSSZOPF_VERSION="$2" \
        -t "ghcr.io/lchristmann/nusszopf-web:$2" "$1" >/dev/null
}

# The operator's files as .github/workflows/release.yml attaches them to a release.
release_files() {
    mkdir -p "$3"
    cp "$1/docker-compose.yaml" "$3/docker-compose.yaml"
    sed "s|^NUSSZOPF_VERSION=.*|NUSSZOPF_VERSION=$2|" "$1/.env.production.example" > "$3/env.production.example"
    # An installer from before P16-04 (rc.1 and earlier) asks for the dotted name, which a real release cannot serve.
    if grep -q 'NUSSZOPF_BASE_URL/\.env\.production\.example' "$1/scripts/install.sh"; then
        cp "$3/env.production.example" "$3/.env.production.example"
    fi
    cp "$1/scripts/install.sh" "$3/install.sh"
}

node_in_browser_image() {
    docker run --rm --network host --ipc host \
        -v "$ROOT:/var/www" -v nusszopf-prod-e2e-node-modules:/var/www/node_modules -v "$WORK:/work" -w /var/www \
        "$PLAYWRIGHT_IMAGE" sh -c "[ -d node_modules/@playwright/test ] || npm ci --no-audit --no-fund >/dev/null; $*"
}

step "Build the old release ($FROM) and this working copy"
git -C "$ROOT" worktree add --detach "$WORK/from" "$FROM" >/dev/null 2>&1 || fail "cannot check out $FROM"
build "$WORK/from" "$OLD"
build "$ROOT" "$NEW"
release_files "$WORK/from" "$OLD" "$WORK/release-old"
release_files "$ROOT" "$NEW" "$WORK/release-new"

step "Install the old release with its own install.sh and start it"
mkdir "$WORK/host"
cd "$WORK/host"
NUSSZOPF_BASE_URL="file://$WORK/release-old" sh "$WORK/release-old/install.sh" "$BASE" "$OLD" >/dev/null
set_value APP_PORT "$PORT"
grep -q '^MAIL_FROM_ADDRESS=$' .env && set_value MAIL_FROM_ADDRESS upgrade@example.test
set_value MAIL_HOST mailpit
set_value MAIL_PORT 1025
set_value LOCATIONIQ_KEY nusszopf-e2e-stub-key
sed -i '/^NUSSZOPF_REGISTER_LIMIT=/d' .env
cat >> .env <<ENV

# Test-only (scripts/upgrade-test.sh, as scripts/prod-e2e.sh)
NUSSZOPF_REGISTER_LIMIT=10000
LOCATIONIQ_URL=http://locationiq-stub/v1/autocomplete.php
SEARCH_PAGE_SIZE=5
ENV
mkdir -p legal
printf '## Impressum\n\nUpgrade-Test-Impressum\n' > legal/legal-notice.md
docker compose up -d --wait --wait-timeout 420 >/dev/null 2>&1 || fail "the old release did not become healthy"
docker compose exec -T php-fpm php artisan nusszopf:health | grep -q "Nusszopf $OLD" || fail "the old release does not report $OLD"

step "Fill it: the dataset, a signed-in browser, work still queued"
docker compose cp "$ROOT/tests/Upgrade/seed.php" php-fpm:/tmp/seed.php >/dev/null 2>&1
docker compose cp "$ROOT/tests/Upgrade/inflight.php" php-fpm:/tmp/inflight.php >/dev/null 2>&1
docker compose exec -T php-fpm php /tmp/seed.php > "$WORK/seed.json" || fail "seed.php failed"
python3 -c 'import json, sys; print("seeded:", json.load(open(sys.argv[1]))["counts"])' "$WORK/seed.json"
node_in_browser_image node tests/Upgrade/session.mjs save "$BASE" p9user05 /work/session.json || fail "cannot sign in on the old release"
# Let the old release's worker index the dataset first; only the work queued below may cross the upgrade.
queue_length() { docker compose exec -T redis sh -c 'redis-cli llen "$(redis-cli --scan --pattern "*queues:default" | head -1)"'; }
i=0
until [ "$(queue_length)" = "0" ]; do
    i=$((i + 1))
    [ "$i" -le 60 ] || fail "the old release's queue did not drain"
    sleep 2
done
docker compose stop queue-worker >/dev/null 2>&1
QUEUED="$(docker compose exec -T php-fpm php /tmp/inflight.php)" || fail "inflight.php failed"
echo "$QUEUED"
FAILED_BEFORE="$(q "select count(*) from failed_jobs")"

step "Snapshot, and the pre-upgrade backup (operations.md, \"Upgrades\" step 2)"
sh "$ROOT/tests/Upgrade/snapshot.sh" "$WORK/before" >/dev/null
docker compose exec -T postgres sh -c 'pg_dump --format=custom -U "$POSTGRES_USER" -d "$POSTGRES_DB"' > "$WORK/postgres.dump"
[ -s "$WORK/postgres.dump" ] || fail "the documented pre-upgrade backup produced nothing"

step "Upgrade with the documented procedure"
started="$(date +%s)"
NUSSZOPF_BASE_URL="file://$WORK/release-new" sh "$ROOT/scripts/install.sh" --upgrade "$NEW" > "$WORK/upgrade.out" || fail "install.sh --upgrade failed"
docker compose pull --ignore-pull-failures --quiet >/dev/null 2>&1 || true
docker compose up -d --wait --wait-timeout 420 >/dev/null 2>&1 || fail "the upgraded stack did not become healthy"
echo "healthy $(($(date +%s) - started)) s after the upgrade started"
docker compose logs php-fpm 2>&1 | grep -E "DONE|FAIL" | grep -v "Heartbeat" | sed 's/^/  /' || true
docker compose exec -T php-fpm php artisan nusszopf:health | grep -q "Nusszopf $NEW" || fail "the upgraded stack does not report $NEW"
cmp -s docker-compose.yaml "$WORK/release-new/docker-compose.yaml" || fail "the upgrade kept the old docker-compose.yaml (P9-01)"

step "Nothing was lost"
sleep 15 # the jobs queued before the upgrade
sh "$ROOT/tests/Upgrade/snapshot.sh" "$WORK/after" "$WORK/before" >/dev/null
cmp -s "$WORK/before/tables.sha256" "$WORK/after/tables.sha256" \
    || fail "rows or columns that existed before the upgrade changed: $(diff "$WORK/before/tables.sha256" "$WORK/after/tables.sha256")"
cmp -s "$WORK/before/storage.sha256" "$WORK/after/storage.sha256" || fail "stored files (avatars) changed"
grep -q "Pending" "$WORK/after/migrate-status.txt" && fail "migrations are still pending: $(cat "$WORK/after/migrate-status.txt")"
[ "$(wc -l < "$WORK/before/search-docs.jsonl")" = "$(wc -l < "$WORK/after/search-docs.jsonl")" ] || fail "the number of search documents changed"
changed="$(diff "$WORK/before/search-docs.jsonl" "$WORK/after/search-docs.jsonl" | grep '^>' | grep -vc 'P9TOKEN002' || true)"
[ "$changed" = "0" ] || fail "search documents changed other than the one updated by the queued job"
grep -q '"title": "Projekt P9TOKEN002 INFLIGHT umbenannt"' "$WORK/after/search-docs.jsonl" || fail "the index job queued before the upgrade did not run"
grep -A3 '"filterableAttributes"' "$WORK/after/search-settings.json" | grep -q '"req_type"' || fail "the upgrade did not apply the index settings"
[ "$(q "select count(*) from failed_jobs")" = "$FAILED_BEFORE" ] || fail "jobs failed during the upgrade: $(docker compose exec -T php-fpm php artisan queue:failed)"
mails="$(curl -s "http://127.0.0.1:${E2E_MAILPIT_PORT:-18025}/api/v1/messages?limit=500")"
for pair in reset:p9user11 newsletter:p9inflight contact:p9user06; do
    case "$QUEUED" in *"${pair%%:*}"*) echo "$mails" | grep -q "${pair#*:}@example.test" || fail "the ${pair%%:*} mail queued before the upgrade was not sent" ;; esac
done
echo "tables, stored files, search documents and settings intact; queued work done: $QUEUED"

step "Everything works on the old data"
node_in_browser_image node tests/Upgrade/session.mjs check "$BASE" p9user05 /work/session.json || fail "the session from before the upgrade was lost"
link() { python3 -c 'import json, sys; d = json.load(open(sys.argv[1])); print(eval(sys.argv[2], {}, {"d": d}) or "")' "$WORK/seed.json" "$1" 2>/dev/null || true; }
verify="$(link "d.get('verify', {}).get('url')")"
if [ -n "$verify" ]; then
    curl -s -o /dev/null "$verify"
    [ "$(q "select email_verified_at is not null from users where name = 'p9user03'")" = "t" ] || fail "the verification link from before the upgrade did not verify"
fi
subscribe="$(link "d['newsletter_links'].get('subscribe')")"
if [ -n "$subscribe" ]; then
    [ "$(curl -s -o /dev/null -w '%{http_code}' "$subscribe")" = "200" ] || fail "the newsletter confirmation link from before the upgrade failed"
    [ "$(q "select confirmed_at is not null from leads where email = 'p9user04@example.test'")" = "t" ] || fail "the newsletter confirmation link did not confirm"
fi
node_in_browser_image node tests/Upgrade/journeys.mjs "$BASE" "$(link "d.get('password_reset', {}).get('url')" | sed 's/^$/-/')" || fail "a journey on the old data failed"
curl -s "$BASE/legalNotice" | grep -q "Upgrade-Test-Impressum" || fail "the legal pages do not come from ./legal after the upgrade"
docker compose exec -T php-fpm sh -c 'mkdir -p storage/app/public/avatars && echo upgrade > storage/app/public/avatars/upgrade.txt'
[ "$(curl -s "$BASE/storage/avatars/upgrade.txt")" = "upgrade" ] || fail "web does not serve a file written after the upgrade (the storage mount)"

if [ "$SUITE" -eq 1 ]; then
    step "The whole Playwright suite on the upgraded installation (desktop browsers)"
    docker compose exec -T php-fpm php artisan cache:clear >/dev/null
    MEILI_KEY="$(grep '^MEILISEARCH_KEY=' .env | cut -d= -f2)"
    docker run --rm --network host --ipc host \
        -v "$ROOT:/var/www" -v nusszopf-prod-e2e-node-modules:/var/www/node_modules -w /var/www \
        -v /var/run/docker.sock:/var/run/docker.sock -v "$(command -v docker):/usr/local/bin/docker:ro" \
        -e PLAYWRIGHT_BASE_URL="$BASE" -e E2E_MAILPIT_URL="http://127.0.0.1:${E2E_MAILPIT_PORT:-18025}" -e E2E_SEARCH_PAGE_SIZE=5 \
        -e E2E_MEILISEARCH_URL="http://127.0.0.1:${E2E_MEILISEARCH_PORT:-17700}" -e E2E_MEILISEARCH_KEY="$MEILI_KEY" \
        -e E2E_REINDEX_COMMAND="docker exec $(docker compose ps -q php-fpm) php artisan search:reindex" \
        "$PLAYWRIGHT_IMAGE" sh -c "npm ci --no-audit --no-fund >/dev/null && npx playwright test --reporter=list --retries=2 --workers=4 \
            --output=test-results/upgrade --project=chromium --project=firefox --project=webkit --grep-invert 'aria snapshots'; \
            status=\$?; chown -R $(id -u):$(id -g) test-results playwright-report 2>/dev/null; exit \$status" \
        || fail "the Playwright suite failed on the upgraded installation"
fi
