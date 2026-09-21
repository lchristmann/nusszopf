#!/bin/sh
# Installs Nusszopf next to this script's working directory: downloads the release's
# docker-compose.yaml and configuration template, generates the secrets, and writes `.env`.
#
#   sh install.sh <app-url> [version]
#
#   <app-url>   the public address, e.g. https://nusszopf.example.org
#   [version]   a release such as 0.1.0; the latest release when omitted
#
# Needs: curl, openssl, and Docker with the Compose plugin. Nothing is started; the last lines say what to run.
set -eu

APP_URL="${1:-}"
VERSION="${2:-latest}"

if [ -z "$APP_URL" ]; then
    echo "Usage: sh install.sh <app-url> [version]   e.g. sh install.sh https://nusszopf.example.org" >&2
    exit 2
fi

for tool in curl openssl docker; do
    command -v "$tool" >/dev/null 2>&1 || { echo "$tool is required but not installed." >&2; exit 1; }
done
docker compose version >/dev/null 2>&1 || { echo "The Docker Compose plugin is required (docker compose)." >&2; exit 1; }

if [ -e .env ]; then
    echo ".env already exists; refusing to overwrite it. Move it away to install again." >&2
    exit 1
fi

if [ -z "${NUSSZOPF_BASE_URL:-}" ]; then
    if [ "$VERSION" = "latest" ]; then
        NUSSZOPF_BASE_URL="https://github.com/lchristmann/nusszopf/releases/latest/download"
    else
        NUSSZOPF_BASE_URL="https://github.com/lchristmann/nusszopf/releases/download/$VERSION"
    fi
fi

echo "Downloading from $NUSSZOPF_BASE_URL ..."
curl -fsSL "$NUSSZOPF_BASE_URL/docker-compose.yaml" -o docker-compose.yaml
curl -fsSL "$NUSSZOPF_BASE_URL/.env.production.example" -o .env.production.example

# The release asset already names its version; fill in the secrets and the address.
set_value() {
    # set_value KEY VALUE — replaces the (empty) `KEY=` line; values are hex/base64/URL text without `|`.
    sed -i.bak "s|^$1=.*|$1=$2|" .env && rm -f .env.bak
}

cp .env.production.example .env
chmod 600 .env
set_value APP_URL "$APP_URL"
set_value APP_KEY "base64:$(openssl rand -base64 32)"
set_value DB_PASSWORD "$(openssl rand -hex 24)"
set_value MEILISEARCH_KEY "$(openssl rand -hex 24)"
set_value HEALTH_TOKEN "$(openssl rand -hex 16)"

case "$APP_URL" in
    https://*) ;;
    *) set_value SESSION_SECURE_COOKIE false ;;
esac

VERSION_LINE="$(grep '^NUSSZOPF_VERSION=' .env || true)"
if [ "$VERSION_LINE" = "NUSSZOPF_VERSION=" ]; then
    if [ "$VERSION" = "latest" ]; then
        echo "The downloaded template names no version. Set NUSSZOPF_VERSION in .env to a release, e.g. 0.1.0." >&2
    else
        set_value NUSSZOPF_VERSION "$VERSION"
    fi
fi

cat <<DONE

Nusszopf is configured in $(pwd):
  .env                 secrets were generated — back this file up (it holds APP_KEY)
  docker-compose.yaml  the stack

Optionally edit .env now: MAIL_*, LOCATIONIQ_KEY, APP_BIND=127.0.0.1 when a reverse proxy runs on this host.

Then start it and check it:
  docker compose up -d
  docker compose exec php-fpm php artisan nusszopf:health

Open $APP_URL and register your account — there is no separate administrator to create.
DONE
