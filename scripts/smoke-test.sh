#!/bin/sh
# Builds the production images from this working copy and runs the operator's stack (docker-compose.yaml)
# from a clean directory, the way the documentation tells an operator to — install.sh included — then checks
# that it comes up healthy and serves pages. Used by CI and by contributors before touching anything in docker/.
#
#   sh scripts/smoke-test.sh            # from the repository root; needs Docker, curl, openssl
#
# Environment: SMOKE_PORT (default 18080), SMOKE_KEEP=1 to leave the stack running afterwards.
set -eu

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${SMOKE_PORT:-18080}"
VERSION="smoke"
WORK="$(mktemp -d)"
PROJECT="nusszopf-smoke"
BASE="http://127.0.0.1:$PORT"

compose() { NUSSZOPF_BUILD_CONTEXT="$ROOT" docker compose -p "$PROJECT" -f docker-compose.yaml -f "$ROOT/compose.prod.yaml" "$@"; }

cleanup() {
    status=$?
    if [ "${SMOKE_KEEP:-0}" != "1" ]; then
        (cd "$WORK" && compose down -v --remove-orphans >/dev/null 2>&1) || true
        rm -rf "$WORK"
    else
        echo "Stack left running in $WORK (project $PROJECT)."
    fi
    [ "$status" -eq 0 ] && echo "SMOKE TEST PASSED" || echo "SMOKE TEST FAILED"
    exit "$status"
}
trap cleanup EXIT

step() { printf '\n== %s\n' "$1"; }
fail() { echo "FAIL: $1" >&2; (cd "$WORK" && compose logs --tail 40 2>&1 | tail -80) || true; exit 1; }

step "Install into a clean directory (install.sh, release assets taken from this working copy)"
mkdir "$WORK/assets"
cp "$ROOT/docker-compose.yaml" "$WORK/assets/docker-compose.yaml"
sed "s|^NUSSZOPF_VERSION=.*|NUSSZOPF_VERSION=$VERSION|" "$ROOT/.env.production.example" > "$WORK/assets/.env.production.example"
cd "$WORK"
NUSSZOPF_BASE_URL="file://$WORK/assets" sh "$ROOT/scripts/install.sh" "$BASE" >/dev/null
sed -i "s|^APP_PORT=.*|APP_PORT=$PORT|" .env
grep -q "@nusszopf.org" .env && fail "a fresh .env names the historical project's mailbox (P-8, P8-03)"
# The one REQUIRED value install.sh cannot know (the operator's sender address): Compose must refuse without it.
compose config >/dev/null 2>config.err && fail "Compose starts without MAIL_FROM_ADDRESS (P-8, P8-03)"
grep -q "Set MAIL_FROM_ADDRESS" config.err || fail "Compose does not name the missing MAIL_FROM_ADDRESS: $(cat config.err)"
rm config.err
sed -i "s|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=smoke@example.test|" .env
grep -q "^APP_KEY=base64:" .env || fail "install.sh did not generate APP_KEY"

step "Build the images and start the stack (waits for every healthcheck)"
compose up -d --build --wait --wait-timeout 420 || fail "the stack did not become healthy"

step "The search index has its configured settings from the first start, before any reindex (P-7, P7-01)"
settings="$(compose exec -T php-fpm sh -c 'curl -s -H "Authorization: Bearer $MEILISEARCH_KEY" "$MEILISEARCH_HOST/indexes/items/settings"')"
echo "$settings" | grep -q '"filterableAttributes":\[[^]]*"req_type"' || fail "the index cannot filter by request category: $settings"
echo "$settings" | grep -q '"maxTotalHits":100000' || fail "the index caps its hits at Meilisearch's default: $settings"
echo "$settings" | grep -q '"updated_at:desc"' || fail "the index lacks the updated_at ranking rule: $settings"

step "The release is baked into the images"
for image in nusszopf-php-fpm nusszopf-web; do
    found="$(docker image inspect "ghcr.io/lchristmann/$image:$VERSION" --format '{{ index .Config.Labels "org.opencontainers.image.version" }}')"
    [ "$found" = "$VERSION" ] || fail "$image reports version '$found', expected '$VERSION'"
done
compose exec -T php-fpm php artisan nusszopf:health | grep -q "Nusszopf $VERSION" || fail "the app does not report version $VERSION"

step "Pages and assets are served"
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/up")" = "200" ] || fail "/up is not 200"
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/search")" = "200" ] || fail "/search is not 200"
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/login")" = "200" ] || fail "/login is not 200"
asset="$(curl -s "$BASE/login" | grep -o '/build/assets/[^"]*\.css' | head -1)"
[ -n "$asset" ] || fail "the login page links no built stylesheet"
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE$asset")" = "200" ] || fail "the built stylesheet $asset is not served"

step "The public shell: Home, robots.txt/sitemap on APP_URL, legal pages from ./legal"
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/")" = "200" ] || fail "Home is not 200"
curl -s "$BASE/robots.txt" | grep -qF "Sitemap: $BASE/sitemap.xml" || fail "robots.txt does not name this instance's sitemap"
curl -s "$BASE/sitemap.xml" | grep -qF "<loc>$BASE/legalNotice</loc>" || fail "the sitemap does not list the static pages on APP_URL"
curl -s "$BASE/legalNotice" | grep -q 'data-test="legal-not-configured"' || fail "an unconfigured Impressum does not say so"
printf '## Smoke\n\nSmoke-Test-Impressum\n' > legal/legal-notice.md
curl -s "$BASE/legalNotice" | grep -q "Smoke-Test-Impressum" || fail "the Impressum does not come from ./legal/legal-notice.md"

step "Uploaded files (avatars) are served by web through the read-only storage mount"
compose exec -T php-fpm sh -c 'mkdir -p storage/app/public/avatars && echo smoke-test > storage/app/public/avatars/smoke.txt'
[ "$(curl -s "$BASE/storage/avatars/smoke.txt")" = "smoke-test" ] || fail "web does not serve a file php-fpm wrote to the public disk"

step "Migrations ran and the caches are warm"
compose exec -T php-fpm php artisan migrate:status | grep -q "Ran" || fail "no migration ran"
compose exec -T php-fpm sh -c 'test -f bootstrap/cache/config.php && test -f bootstrap/cache/routes-v7.php' || fail "the framework caches were not built"

step "Debug mode is off and details are not leaked"
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/no-such-page")" = "404" ] || fail "an unknown page is not a 404"
curl -s "$BASE/no-such-page" | grep -qi "stack trace\|vendor/laravel" && fail "an error page leaks debug output"
curl -s "$BASE/no-such-page" | grep -q "404 – Nusszopf verknetet..." || fail "an unknown page does not show the Nusszopf error page"

step "Security headers, no version disclosure, links on APP_URL whatever the Host (P-4)"
headers="$(curl -sI "$BASE/login")"
for header in "Content-Security-Policy: default-src 'self'" "X-Content-Type-Options: nosniff" "X-Frame-Options: SAMEORIGIN" "Referrer-Policy: strict-origin-when-cross-origin"; do
    echo "$headers" | grep -qiF "$header" || fail "a page is missing the header '$header'"
done
[ "$(echo "$headers" | grep -ciF "X-Content-Type-Options")" = "1" ] || fail "a page carries X-Content-Type-Options twice"
echo "$headers" | grep -qi "^X-Powered-By" && fail "a page names the PHP version (X-Powered-By)"
echo "$headers" | grep -qi "^Server: nginx/" && fail "a page names the nginx version"
curl -sI "$BASE/storage/avatars/smoke.txt" | grep -qi "X-Content-Type-Options: nosniff" || fail "an uploaded file is served without nosniff"
curl -s -H "Host: evil.example" "$BASE/login" | grep -q "evil.example" && fail "a page builds links from a forged Host header"

step "Dependencies, scheduler and queue worker report healthy (they heartbeat once a minute)"
i=0
until [ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/health")" = "200" ]; do
    i=$((i + 1))
    [ "$i" -le 40 ] || fail "/health is still not 200 after 200 s"
    sleep 5
done
token="$(grep '^HEALTH_TOKEN=' .env | cut -d= -f2)"
details="$(curl -s -H "Authorization: Bearer $token" "$BASE/health")"
echo "$details" | grep -q "\"version\":\"$VERSION\"" || fail "/health with the token does not report the version: $details"
curl -s "$BASE/health" | grep -q '"checks"' && fail "/health leaks details without the token"

step "Search recovery command runs against the production stack"
compose exec -T php-fpm php artisan search:reindex >/dev/null || fail "search:reindex failed"

echo
