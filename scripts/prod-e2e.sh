#!/bin/sh
# P-7 (docs/release/parity/P-07-production-e2e.md): runs the whole Playwright suite against the production images.
# Builds the images from this working copy, installs the operator's stack (docker-compose.yaml) into a clean
# directory with install.sh, as smoke-test.sh does, adds the test doubles of tests/E2E/production/compose.e2e.yaml
# (Mailpit for SMTP, the LocationIQ stub), starts it, and runs Playwright from the pinned Playwright image.
#
#   sh scripts/prod-e2e.sh                       # desktop projects, then the device projects
#   sh scripts/prod-e2e.sh --project=chromium    # arguments are passed to `playwright test` (one pass)
#
# Environment: PROD_E2E_PORT (default 18080), PROD_E2E_KEEP=1 to leave the stack running, PROD_E2E_WORKERS (default 4).
# Needs Docker, curl and openssl. Test-only settings (appended to the installed .env) are marked below.
set -eu

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${PROD_E2E_PORT:-18080}"
VERSION="e2e"
PROJECT="nusszopf-prod-e2e"
WORK="$(mktemp -d)"
BASE="http://127.0.0.1:$PORT"
PLAYWRIGHT_IMAGE="mcr.microsoft.com/playwright:v1.63.0-noble"

compose() {
    NUSSZOPF_BUILD_CONTEXT="$ROOT" docker compose -p "$PROJECT" -f docker-compose.yaml -f "$ROOT/compose.prod.yaml" \
        -f "$ROOT/tests/E2E/production/compose.e2e.yaml" "$@"
}

cleanup() {
    status=$?
    if [ "${PROD_E2E_KEEP:-0}" != "1" ]; then
        (cd "$WORK" && compose down -v --remove-orphans >/dev/null 2>&1) || true
        rm -rf "$WORK"
    else
        echo "Stack left running in $WORK (project $PROJECT)."
    fi
    [ "$status" -eq 0 ] && echo "PRODUCTION E2E PASSED" || echo "PRODUCTION E2E FAILED"
    exit "$status"
}
trap cleanup EXIT

step() { printf '\n== %s\n' "$1"; }

step "Install into a clean directory (install.sh, release assets taken from this working copy)"
mkdir "$WORK/assets"
cp "$ROOT/docker-compose.yaml" "$WORK/assets/docker-compose.yaml"
sed "s|^NUSSZOPF_VERSION=.*|NUSSZOPF_VERSION=$VERSION|" "$ROOT/.env.production.example" > "$WORK/assets/env.production.example"
cd "$WORK"
NUSSZOPF_BASE_URL="file://$WORK/assets" sh "$ROOT/scripts/install.sh" "$BASE" >/dev/null

set_value() { sed -i "s|^$1=.*|$1=$2|" .env; }
set_value APP_PORT "$PORT"
# The one REQUIRED value install.sh cannot know (the operator's sender address).
set_value MAIL_FROM_ADDRESS e2e@example.test
# --- Test-only settings: the test doubles, and the limits the suite needs (as compose.dev.yaml) ---------------
set_value MAIL_HOST mailpit
set_value MAIL_PORT 1025
set_value LOCATIONIQ_KEY nusszopf-e2e-stub-key
set_value NUSSZOPF_REGISTER_LIMIT 10000
cat >> .env <<ENV

# Test-only (scripts/prod-e2e.sh)
LOCATIONIQ_URL=http://locationiq-stub/v1/autocomplete.php
SEARCH_PAGE_SIZE=5
ENV

step "Build the images and start the stack (waits for every healthcheck)"
compose up -d --build --wait --wait-timeout 420

MEILI_KEY="$(grep '^MEILISEARCH_KEY=' .env | cut -d= -f2)"
PHP_CONTAINER="$(compose ps -q php-fpm)"

playwright() {
    docker run --rm --network host --ipc host \
        -v "$ROOT:/var/www" -v nusszopf-prod-e2e-node-modules:/var/www/node_modules -w /var/www \
        -v /var/run/docker.sock:/var/run/docker.sock -v "$(command -v docker):/usr/local/bin/docker:ro" \
        -e PLAYWRIGHT_BASE_URL="$BASE" \
        -e E2E_MAILPIT_URL="http://127.0.0.1:${E2E_MAILPIT_PORT:-18025}" \
        -e E2E_SEARCH_PAGE_SIZE=5 \
        -e E2E_MEILISEARCH_URL="http://127.0.0.1:${E2E_MEILISEARCH_PORT:-17700}" \
        -e E2E_MEILISEARCH_KEY="$MEILI_KEY" \
        -e E2E_REINDEX_COMMAND="docker exec $PHP_CONTAINER php artisan search:reindex" \
        "$PLAYWRIGHT_IMAGE" sh -c "npm ci --no-audit --no-fund >/dev/null && npx playwright test --reporter=list --retries=2 --workers=${PROD_E2E_WORKERS:-4} --output=test-results/prod-e2e $*; status=\$?; chown -R $(id -u):$(id -g) test-results playwright-report 2>/dev/null; exit \$status"
}

# Two retries, as the CI jobs on the dev stack have (playwright.config.ts, `CI`): a test that passes on its retry is
# listed as "flaky" in the output, not hidden (P-16, P16-08). The aria-snapshot dump reads the visual reference dataset,
# which only development seeds; it is not a test.
if [ "$#" -gt 0 ]; then
    step "Playwright: $*"
    playwright --grep-invert "'aria snapshots'" "$@"
else
    step "Playwright: desktop projects"
    desktop=0
    playwright --grep-invert "'aria snapshots'" --project=chromium --project=firefox --project=webkit || desktop=$?
    # All projects share one address, and so the historical per-IP newsletter budget (docs/testing/README.md).
    compose exec -T php-fpm php artisan cache:clear >/dev/null
    step "Playwright: device projects"
    devices=0
    playwright --grep-invert "'aria snapshots'" --project=mobile-safari --project=mobile-chrome --project=tablet-safari || devices=$?
    [ "$desktop" -eq 0 ] && [ "$devices" -eq 0 ]
fi
