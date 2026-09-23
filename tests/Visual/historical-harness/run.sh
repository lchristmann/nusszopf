#!/usr/bin/env sh
# Runs the historical Nusszopf webapp locally as the visual reference (docs/testing/visual-regression.md).
# Copies ../historical/{web,be}-nusszopf into a work folder (the historical checkouts stay untouched), installs
# the locked dependencies with Node 12, replaces the Auth0 SDK with a cookie-driven fake session, builds the
# Next.js app, starts it with its Hasura, PostgreSQL and Meilisearch on the development stack's network, and
# seeds tests/Visual/reference-data.json. Needs the development stack running (compose.dev.yaml).
#   tests/Visual/historical-harness/run.sh          # set up and start
#   tests/Visual/historical-harness/run.sh down     # stop and remove the containers
set -e
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE/../../.." && pwd)"
HISTORICAL="${HISTORICAL:-$ROOT/../historical}"
export HARNESS_WORK="${HARNESS_WORK:-$ROOT/.historical-harness}"
export HARNESS_UID="$(id -u)" HARNESS_GID="$(id -g)"
COMPOSE="docker compose -f $HERE/compose.yaml"

if [ "$1" = "down" ]; then
    $COMPOSE down -v
    exit 0
fi

if [ ! -d "$HARNESS_WORK/web/node_modules" ]; then
    mkdir -p "$HARNESS_WORK"
    rsync -a --delete --exclude .git "$HISTORICAL/web-nusszopf/" "$HARNESS_WORK/web/"
    rsync -a --delete --exclude .git "$HISTORICAL/be-nusszopf/" "$HARNESS_WORK/be/"
    docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -v "$HARNESS_WORK/web:/app" -w /app node:12-bullseye \
        yarn install --frozen-lockfile --ignore-scripts --network-timeout 600000
fi

# The only changes to the historical code: the webapp's fake session, and a `next start`-able build target for
# the webapp and the two Auth0-hosted apps (login/sign-up/forgot password, set new password). Those two render
# their forms without Auth0 as long as no Auth0 query parameters are given.
cp "$HERE/auth0.stub.js" "$HARNESS_WORK/web/projects/webapp/src/utils/libs/auth0.js"
for app in webapp auth-login auth-password; do
    sed -i "s/target: 'serverless',/target: 'server',/" "$HARNESS_WORK/web/projects/$app/next.config.js"
    docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp --env-file "$HERE/hist.env" -v "$HARNESS_WORK/web:/app" \
        -w "/app/projects/$app" node:12-bullseye /app/node_modules/.bin/next build
done

$COMPOSE up -d
echo "Waiting for Hasura to apply the historical migrations..."
until docker run --rm --network nusszopf-dev_default curlimages/curl -fs http://hist-hasura:8080/healthz >/dev/null 2>&1; do sleep 2; done
$COMPOSE exec -T hist-postgres psql -q -U postgres -c 'TRUNCATE users, leads, projects, requests, projects_analytics CASCADE'
docker run --rm --network nusszopf-dev_default -v "$ROOT/tests/Visual:/v" -w /v/historical-harness node:20-alpine node seed.mjs \
    | $COMPOSE exec -T hist-postgres psql -q -U postgres
echo "Historical apps on the nusszopf-dev_default network: http://hist-web:3000, http://hist-auth-login:3001, http://hist-auth-password:3002"
