#!/bin/sh
# Installs Nusszopf next to this script's working directory: downloads the release's
# docker-compose.yaml and configuration template, generates the secrets, and writes `.env`.
# With --upgrade, prepares an existing installation for another release instead.
#
#   sh install.sh <app-url> [version]
#   sh install.sh --upgrade [version]
#
#   <app-url>   the public address, e.g. https://nusszopf.example.org
#   [version]   a release such as 0.1.0; the latest release when omitted
#
# --upgrade runs in the installation's directory. It replaces docker-compose.yaml and .env.production.example
# with the release's (keeping the old ones as *.previous, and .env as .env.previous), sets NUSSZOPF_VERSION,
# and lists what .env lacks or should change. The rest of .env is kept as it is.
#
# Needs: curl, openssl, and Docker with the Compose plugin. Nothing is started; the last lines say what to run.
set -eu

MODE=install
if [ "${1:-}" = "--upgrade" ]; then
    MODE=upgrade
    APP_URL=""
    VERSION="${2:-latest}"
else
    APP_URL="${1:-}"
    VERSION="${2:-latest}"
    if [ -z "$APP_URL" ]; then
        echo "Usage: sh install.sh <app-url> [version]   e.g. sh install.sh https://nusszopf.example.org" >&2
        echo "       sh install.sh --upgrade [version]" >&2
        exit 2
    fi
fi

for tool in curl openssl docker; do
    command -v "$tool" >/dev/null 2>&1 || { echo "$tool is required but not installed." >&2; exit 1; }
done
docker compose version >/dev/null 2>&1 || { echo "The Docker Compose plugin is required (docker compose)." >&2; exit 1; }

if [ -z "${NUSSZOPF_BASE_URL:-}" ]; then
    if [ "$VERSION" = "latest" ]; then
        NUSSZOPF_BASE_URL="https://github.com/lchristmann/nusszopf/releases/latest/download"
    else
        NUSSZOPF_BASE_URL="https://github.com/lchristmann/nusszopf/releases/download/$VERSION"
    fi
fi

set_value() {
    # set_value KEY VALUE — replaces the `KEY=` line; values are hex/base64/URL text without `|`.
    sed -i.bak "s|^$1=.*|$1=$2|" .env && rm -f .env.bak
}

if [ "$VERSION" = "latest" ]; then DOCS_REF=main; else DOCS_REF="$VERSION"; fi
DOCS="https://github.com/lchristmann/nusszopf/blob/$DOCS_REF/docs/deployment"

if [ "$MODE" = "upgrade" ]; then
    # P-9, finding P9-01: a release may change docker-compose.yaml (a new mount, a new required setting), so an
    # upgrade must bring the release's own file, not only a new NUSSZOPF_VERSION.
    if [ ! -f .env ] || [ ! -f docker-compose.yaml ]; then
        echo "No .env and docker-compose.yaml here. Run --upgrade in your installation's directory." >&2
        exit 1
    fi
    echo "Downloading from $NUSSZOPF_BASE_URL ..."
    curl -fsSL "$NUSSZOPF_BASE_URL/docker-compose.yaml" -o docker-compose.yaml.new
    curl -fsSL "$NUSSZOPF_BASE_URL/.env.production.example" -o .env.production.example.new

    # The release asset names its own version, also for "latest".
    TARGET="$(sed -n 's/^NUSSZOPF_VERSION=//p' .env.production.example.new)"
    if [ -z "$TARGET" ]; then
        rm -f docker-compose.yaml.new .env.production.example.new
        echo "The downloaded template names no version; nothing was changed. Name the release: sh install.sh --upgrade 0.2.0" >&2
        exit 1
    fi
    CURRENT="$(sed -n 's/^NUSSZOPF_VERSION=//p' .env)"
    DOCS="https://github.com/lchristmann/nusszopf/blob/$TARGET/docs/deployment"

    # Run again for the same release, the *.previous files already hold the release before it: keep them.
    if [ "$CURRENT" != "$TARGET" ]; then
        cp -p .env .env.previous
        mv docker-compose.yaml docker-compose.yaml.previous
        if [ -f .env.production.example ]; then mv .env.production.example .env.production.example.previous; fi
    fi
    mv docker-compose.yaml.new docker-compose.yaml
    mv .env.production.example.new .env.production.example
    set_value NUSSZOPF_VERSION "$TARGET"
    mkdir -p legal

    # Settings this release knows that .env does not name: they take their defaults until you add them.
    NEW_SETTINGS="$(grep -o '^[A-Z][A-Z0-9_]*=' .env.production.example | tr -d = | while read -r key; do
        grep -q "^$key=" .env || echo "  $key"
    done)"
    # Settings docker-compose.yaml requires (it refuses to start without them) that are empty in .env.
    MISSING="$(grep -o '\${[A-Z][A-Z0-9_]*:?' docker-compose.yaml | sed 's/^\${//; s/:?$//' | sort -u | while read -r key; do
        [ -n "$(sed -n "s/^$key=//p" .env | tr -d '"')" ] || echo "  $key"
    done)"
    # Values earlier releases wrote into .env that are no longer safe defaults.
    LEGACY=""
    if grep -q '^TRUSTED_PROXIES=\*' .env; then
        LEGACY="$LEGACY
  TRUSTED_PROXIES=*     trusts every client's claimed address, which defeats the per-address limits on login,
                        registration and the public forms. Use the template's list of private networks, or your
                        proxy's address (docs/deployment/README.md, \"Reverse proxy and TLS\")."
    fi
    if grep -q '^MAIL_FROM_ADDRESS=.*@nusszopf\.org' .env; then
        LEGACY="$LEGACY
  MAIL_FROM_ADDRESS     is the historical project's mailbox. Set your own sender address; it is also shown as
                        this instance's contact unless NUSSZOPF_CONTACT_EMAIL is set."
    fi

    cat <<DONE

Prepared the upgrade from ${CURRENT:-an unknown version} to $TARGET in $(pwd):
  docker-compose.yaml         replaced by $TARGET's (the old one is docker-compose.yaml.previous)
  .env                        NUSSZOPF_VERSION=$TARGET; everything else unchanged (the old file is .env.previous)
  .env.production.example     $TARGET's template, to compare your .env with
DONE
    if [ -n "$MISSING" ]; then
        printf '\nREQUIRED, empty in .env — docker compose refuses to start until you set them:\n%s\n' "$MISSING"
    fi
    if [ -n "$LEGACY" ]; then
        printf '\nPlease change in .env:%s\n' "$LEGACY"
    fi
    if [ -n "$NEW_SETTINGS" ]; then
        printf '\nNew settings, not in your .env (their defaults apply; see .env.production.example):\n%s\n' "$NEW_SETTINGS"
    fi
    cat <<DONE

Then start it and check it:
  docker compose pull
  docker compose up -d                                       # applies the database migrations on start
  docker compose ps                                          # wait until every service is "healthy"
  docker compose exec php-fpm php artisan nusszopf:health    # shows $TARGET

Documentation: $DOCS/operations.md ("Upgrades", "Rollback")
DONE
    exit 0
fi

if [ -e .env ]; then
    echo ".env already exists; refusing to overwrite it. To move this installation to another release, run" >&2
    echo "  sh install.sh --upgrade <version>   (docs/deployment/operations.md, \"Upgrades\"); to install again, move .env away." >&2
    exit 1
fi

echo "Downloading from $NUSSZOPF_BASE_URL ..."
curl -fsSL "$NUSSZOPF_BASE_URL/docker-compose.yaml" -o docker-compose.yaml
curl -fsSL "$NUSSZOPF_BASE_URL/.env.production.example" -o .env.production.example

# The release asset already names its version; fill in the secrets and the address.
cp .env.production.example .env
# Your Impressum, Rechtliches and Datenschutz texts go here (mounted by docker-compose.yaml).
mkdir -p legal
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

Before starting, edit .env:
  MAIL_FROM_ADDRESS    REQUIRED — your own sender address; docker compose refuses to start without it
  MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD
                       your SMTP relay; without one Nusszopf runs, but no e-mail is delivered
  APP_BIND=127.0.0.1   when a reverse proxy runs on this host
Optional: NUSSZOPF_CONTACT_EMAIL, LOCATIONIQ_KEY, GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.

Put your own legal texts into legal/ as legal-notice.md (Impressum), legal-policy.md (Rechtliches) and
privacy.md (Datenschutz). Until then those pages say they are not configured.

Then start it and check it:
  docker compose up -d
  docker compose ps                                          # wait until every service is "healthy"
  docker compose exec php-fpm php artisan nusszopf:health

Open $APP_URL and register your account — there is no separate administrator to create.

Documentation: $DOCS/README.md (installation, configuration)
               $DOCS/operations.md (health, upgrades, backups, troubleshooting)
DONE
