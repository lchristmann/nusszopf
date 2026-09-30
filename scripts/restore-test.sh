#!/bin/sh
# P-10 (docs/release/parity/P-10-backup-restore.md): the backup/restore drill. Every "host" is its own Docker
# daemon (docker:dind), so the target really starts empty: no images, containers, volumes or files.
#
#   1. Source host: install this working copy's release with install.sh, fill it (tests/Upgrade/seed.php), sign a
#      browser in, and let root's crontab run the backup script exactly as docs/handbuch/backup.md "Das Backup-Skript"
#      prints it.
#   2. Check the backup, copy it off the host, and destroy the source host.
#   3. Target host: prove it is empty, then run the "Wiederherstellen" block of docs/handbuch/backup.md exactly as printed.
#   4. Compare the restored installation with the source and use it: sign-in, privacy, search, avatars, mailed
#      links, legal pages, queue, scheduler, and writing new files.
#   5. With --rollback-from <ref>: install <ref>, back up, upgrade to this working copy, write data, roll back with
#      "Rollback" (= "Restore"), check that exactly the pre-upgrade state is back, then upgrade again.
#
#   sh scripts/restore-test.sh [--suite] [--rollback-from <ref>]
#
#   --suite               also run the Playwright suite (Chromium) against the restored installation
#   --rollback-from <ref> also run the rollback drill from <ref> (any commit from 8c4a2eb on; the previous tag later)
#
# Environment: RESTORE_PORT (default 18110), RESTORE_MAILPIT_PORT (default 18125), RESTORE_KEEP=1 to keep the hosts.
# Needs Docker (privileged containers for docker:dind), git and python3. The Nusszopf images are built locally and
# loaded into each host, standing in for the GHCR pull, so `docker compose pull` there fetches only the third-party
# images; the one change made to the printed Restore block is `--ignore-pull-failures` on that line.
set -eu

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SUITE=0
ROLLBACK_FROM=""
while [ $# -gt 0 ]; do
    case "$1" in
        --suite) SUITE=1 ;;
        --rollback-from) ROLLBACK_FROM="${2:?--rollback-from needs a ref}"; shift ;;
        *) echo "Usage: sh scripts/restore-test.sh [--suite] [--rollback-from <ref>]" >&2; exit 2 ;;
    esac
    shift
done
# RELEASE_TAG=<tag> (P-16): the working copy is a checkout of that release; it and --rollback-from <published tag> are
# pulled from GHCR under their own tags, not built.
NEW="restore-to"
OLD="restore-from"
if [ -n "${RELEASE_TAG:-}" ]; then NEW="$RELEASE_TAG"; OLD="$ROLLBACK_FROM"; fi
PORT="${RESTORE_PORT:-18110}"
MAILPIT_PORT="${RESTORE_MAILPIT_PORT:-18125}"
BASE="http://127.0.0.1:$PORT"
WORK="$(mktemp -d)"
DIND_IMAGE="docker:28-dind"
PLAYWRIGHT_IMAGE="mcr.microsoft.com/playwright:v1.63.0-noble"
HOSTS="nusszopf-restore-source nusszopf-restore-target nusszopf-restore-rollback"

cleanup() {
    status=$?
    if [ "${RESTORE_KEEP:-0}" != "1" ]; then
        for h in $HOSTS; do docker rm -f -v "$h" >/dev/null 2>&1 || true; done
        git -C "$ROOT" worktree remove --force "$WORK/from" >/dev/null 2>&1 || true
        rm -rf "$WORK"
    else
        echo "Hosts kept: $HOSTS; files in $WORK."
    fi
    [ "$status" -eq 0 ] && echo "RESTORE TEST PASSED" || echo "RESTORE TEST FAILED"
    exit "$status"
}
trap cleanup EXIT

step() { printf '\n== %s\n' "$1"; }
fail() { echo "FAIL: $1" >&2; [ -n "${CURRENT:-}" ] && on "$CURRENT" 'docker compose logs --tail 30 php-fpm queue-worker 2>&1 | tail -50' || true; exit 1; }
# on <host> <script>: runs a shell script as root in the host's installation directory.
on() { docker exec -i -w /opt/nusszopf "$1" sh -eu -c "$2"; }
q() { on "$1" "docker compose exec -T postgres sh -c 'psql -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\" -Atc \"\$0\"' \"$2\""; }

# The code block after the n-th "scripts/restore-test.sh runs this block" marker in docs/handbuch/backup.md, as printed.
doc_block() {
    awk -v n="$1" '
        /<!-- P-10: scripts\/restore-test.sh runs this block/ { c++; if (c == n) found = 1; next }
        found && /^```/ { if (inside) exit; inside = 1; next }
        found && inside' "$ROOT/docs/handbuch/backup.md"
}

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

# new_host <name> <versions…>: a new, empty Docker host with the operator's tools and the given releases' images.
new_host() {
    name="$1"; shift
    docker rm -f -v "$name" >/dev/null 2>&1 || true
    docker run -d --privileged --name "$name" -p "127.0.0.1:$PORT:8080" -p "127.0.0.1:$MAILPIT_PORT:8025" "$DIND_IMAGE" >/dev/null
    i=0
    until docker exec "$name" docker info >/dev/null 2>&1; do
        i=$((i + 1)); [ "$i" -le 60 ] || fail "the Docker daemon of $name did not start"; sleep 1
    done
    # curl and openssl for install.sh (docs/handbuch/installation.md); python3 only for tests/Upgrade/snapshot.sh.
    docker exec "$name" apk add -q curl openssl python3 >/dev/null
    empty="$(docker exec "$name" sh -c 'echo "$(docker images -q | wc -l) $(docker ps -aq | wc -l) $(docker volume ls -q | wc -l) $(ls -d /opt/nusszopf* 2>/dev/null | wc -l)"')"
    [ "$empty" = "0 0 0 0" ] || fail "$name is not empty (images, containers, volumes, /opt/nusszopf*: $empty)"
    echo "$name: empty (0 images, 0 containers, 0 volumes, no /opt/nusszopf or /opt/nusszopf-backups)"
    images=""
    for v in "$@"; do images="$images ghcr.io/lchristmann/nusszopf-php-fpm:$v ghcr.io/lchristmann/nusszopf-web:$v"; done
    # shellcheck disable=SC2086
    docker save $images | docker exec -i "$name" docker load >/dev/null
    docker exec "$name" mkdir -p /opt/nusszopf /root/tools
    docker cp "$ROOT/tests/Upgrade/snapshot.sh" "$name:/root/tools/snapshot.sh"
    docker cp "$WORK/restore.sh" "$name:/root/tools/restore.sh"
}

# install_release <host> <release-dir> <version>: install.sh as an operator runs it, plus the drill's test doubles.
install_release() {
    docker cp "$2" "$1:/release"
    docker cp "$ROOT/docker/stubs/locationiq.conf" "$1:/opt/nusszopf/locationiq.conf"
    on "$1" "
        NUSSZOPF_BASE_URL=file:///release sh /release/install.sh '$BASE' '$3' >/dev/null
        s() { sed -i \"s|^\$1=.*|\$1=\$2|\" .env; }
        grep -q '^MAIL_FROM_ADDRESS=\$' .env && s MAIL_FROM_ADDRESS restore@example.test
        s MAIL_HOST mailpit; s MAIL_PORT 1025; s LOCATIONIQ_KEY nusszopf-e2e-stub-key; s SESSION_SECURE_COOKIE false
        sed -i '/^NUSSZOPF_REGISTER_LIMIT=/d' .env
        printf '\n# Test-only (scripts/restore-test.sh)\nNUSSZOPF_REGISTER_LIMIT=10000\nLOCATIONIQ_URL=http://locationiq-stub/v1/autocomplete.php\nSEARCH_PAGE_SIZE=5\n' >> .env
        mkdir -p legal
        printf '## Impressum\n\nRestore-Test-Impressum\n' > legal/legal-notice.md
        # The test doubles, kept the way an operator keeps own changes: in compose.override.yaml, which the backup
        # must carry over to the new host together with the file it mounts.
        cat > compose.override.yaml <<'YAML'
services:
  mailpit:
    image: axllent/mailpit:latest
    ports: ['8025:8025']
    healthcheck: {test: ['CMD', 'wget', '-qO-', 'http://127.0.0.1:8025/api/v1/info'], interval: 5s, timeout: 3s, retries: 10}
  locationiq-stub:
    image: nginx:alpine
    volumes: ['./locationiq.conf:/etc/nginx/conf.d/default.conf:ro']
  php-fpm:
    depends_on:
      mailpit: {condition: service_healthy}
      locationiq-stub: {condition: service_started}
YAML
        docker compose pull --ignore-pull-failures --quiet >/dev/null 2>&1 || true
        docker compose up -d --wait --wait-timeout 420 >/dev/null 2>&1
    " || fail "$3 did not install and become healthy on $1"
}

# seed <host> <out.json>: the P-9 dataset through the release's own models, then an indexed, idle queue.
seed() {
    on "$1" "docker compose exec -T php-fpm sh -c 'cat > /tmp/seed.php'" < "$ROOT/tests/Upgrade/seed.php"
    on "$1" "docker compose exec -T php-fpm php /tmp/seed.php" > "$2" || fail "seed.php failed on $1"
    python3 -c 'import json, sys; print("seeded:", json.load(open(sys.argv[1]))["counts"])' "$2"
    wait_for_idle_queue "$1"
}

wait_for_idle_queue() {
    i=0
    until [ "$(on "$1" 'docker compose exec -T redis sh -c "redis-cli --scan --pattern \"*queues:*\" | xargs -r -n1 redis-cli llen" | awk "{s+=\$1} END {print s+0}"')" = "0" ]; do
        i=$((i + 1)); [ "$i" -le 90 ] || fail "the queue on $1 did not drain"; sleep 2
    done
}

# wait_for_search <host> <documents>: the reindex runs through the queue worker.
wait_for_search() {
    i=0
    until [ "$(on "$1" 'docker compose exec -T php-fpm sh -c "curl -s -H \"Authorization: Bearer \$MEILISEARCH_KEY\" \"\$MEILISEARCH_HOST/indexes/items/stats\""' | python3 -c 'import json, sys; print(json.load(sys.stdin).get("numberOfDocuments", 0))' 2>/dev/null)" = "$2" ]; do
        i=$((i + 1)); [ "$i" -le 90 ] || fail "the search index on $1 did not reach $2 documents"; sleep 2
    done
}

wait_for_health() {
    i=0
    until on "$1" 'docker compose exec -T php-fpm php artisan nusszopf:health' > "$WORK/health.txt" 2>&1; do
        i=$((i + 1)); [ "$i" -le 60 ] || fail "nusszopf:health on $1 did not pass: $(cat "$WORK/health.txt")"; sleep 3
    done
}

in_browser_image() {
    docker run --rm --network host --ipc host \
        -v "$ROOT:/var/www" -v nusszopf-prod-e2e-node-modules:/var/www/node_modules -v "$WORK:/work" -w /var/www \
        "$PLAYWRIGHT_IMAGE" sh -c "[ -d node_modules/@playwright/test ] || npm ci --no-audit --no-fund >/dev/null; $*"
}

link() { python3 -c 'import json, sys; d = json.load(open(sys.argv[1])); print(eval(sys.argv[2], {}, {"d": d}) or "")' "$WORK/seed.json" "$1" 2>/dev/null || true; }

doc_block 1 > "$WORK/nusszopf-backup.sh"
doc_block 2 | sed 's/^docker compose pull$/docker compose pull --ignore-pull-failures/' > "$WORK/restore.sh"
grep -q "pg_dump" "$WORK/nusszopf-backup.sh" || fail "backup.md has no marked backup script"
grep -q "pg_restore" "$WORK/restore.sh" || fail "backup.md has no marked restore block"

step "Build this working copy's release"
build "$ROOT" "$NEW"
release_files "$ROOT" "$NEW" "$WORK/release-new"

# ------------------------------------------------------------------------------------------------------------------
step "Source host: install, fill, sign in"
CURRENT=nusszopf-restore-source
new_host "$CURRENT" "$NEW"
install_release "$CURRENT" "$WORK/release-new" "$NEW"
seed "$CURRENT" "$WORK/seed.json"
in_browser_image node tests/Upgrade/session.mjs save "$BASE" p9user05 /work/session.json || fail "cannot sign in on the source"
on "$CURRENT" 'sh /root/tools/snapshot.sh /root/before' > "$WORK/before.txt" || fail "cannot snapshot the source"
docker cp "$CURRENT:/root/before" "$WORK/before"
FAILED_SOURCE="$(q "$CURRENT" "select count(*) from failed_jobs")"
DOCS="$(sed -n 's/^snapshot .*: \([0-9]*\) search documents.*/\1/p' "$WORK/before.txt")"
head -1 "$WORK/before.txt"

step "Source host: the backup script from backup.md, run by root's crontab"
docker cp "$WORK/nusszopf-backup.sh" "$CURRENT:/usr/local/bin/nusszopf-backup.sh"
docker exec "$CURRENT" sh -c 'chmod 700 /usr/local/bin/nusszopf-backup.sh
    echo "* * * * * /usr/local/bin/nusszopf-backup.sh >> /var/log/nusszopf-backup.log 2>&1" | crontab -
    crond -b'
i=0
until docker exec "$CURRENT" sh -c 'ls -d /opt/nusszopf-backups/*[0-9] >/dev/null 2>&1'; do
    i=$((i + 1)); [ "$i" -le 75 ] || fail "cron wrote no complete backup: $(docker exec "$CURRENT" cat /var/log/nusszopf-backup.log 2>&1)"; sleep 2
done
docker exec "$CURRENT" sh -c 'crontab -r; pkill crond' || true
BACKUP="$(docker exec "$CURRENT" sh -c 'ls -d /opt/nusszopf-backups/*[0-9] | head -1')"
docker exec "$CURRENT" cat /var/log/nusszopf-backup.log
docker exec "$CURRENT" sh -c "ls -la '$BACKUP'"

step "The backup holds what Restore expects"
docker exec "$CURRENT" sh -c "[ \"\$(stat -c %a '$BACKUP')\" = 700 ]" || fail "the backup folder is readable by others"
on "$CURRENT" "docker compose exec -T postgres pg_restore --list < '$BACKUP/postgres.dump'" > "$WORK/dump.list" || fail "postgres.dump is not a readable archive"
for t in users projects project_requests project_analytics leads password_reset_tokens migrations; do
    grep -q "TABLE DATA public $t " "$WORK/dump.list" || fail "postgres.dump has no data for $t"
done
docker exec "$CURRENT" sh -c "tar tzf '$BACKUP/storage.tar.gz'" | sed 's|^\./||' | sort > "$WORK/storage.list"
q "$CURRENT" "select 'public/avatars/' || id || '-v' || avatar_version || '.jpg' from users where avatar_version > 0 order by 1" > "$WORK/avatars.list"
[ -s "$WORK/avatars.list" ] || fail "the source has no avatars to back up"
missing="$(comm -23 "$WORK/avatars.list" "$WORK/storage.list")"
[ -z "$missing" ] || fail "storage.tar.gz lacks avatars: $missing"
docker exec "$CURRENT" sh -c "tar tzf '$BACKUP/installation.tar.gz'" > "$WORK/installation.list"
for f in ./.env ./docker-compose.yaml ./compose.override.yaml ./locationiq.conf ./legal/legal-notice.md; do
    grep -qx "$f" "$WORK/installation.list" || fail "installation.tar.gz lacks $f"
done
key="$(on "$CURRENT" 'grep ^APP_KEY= .env')"
[ "$(docker exec "$CURRENT" sh -c "tar xzOf '$BACKUP/installation.tar.gz' ./.env | grep ^APP_KEY=")" = "$key" ] || fail "the backed-up .env has another APP_KEY"
echo "postgres.dump: $(grep -c 'TABLE DATA' "$WORK/dump.list") tables with data; storage.tar.gz: $(wc -l < "$WORK/avatars.list") referenced avatars of $(grep -c '^public/avatars/.' "$WORK/storage.list") files; installation.tar.gz: .env (same APP_KEY), docker-compose.yaml, compose.override.yaml, legal/"

step "Disaster: copy the backup off the host, destroy the host"
mkdir -p "$WORK/offsite"
docker cp "$CURRENT:$BACKUP" "$WORK/offsite/"
docker rm -f -v "$CURRENT" >/dev/null
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/" || true)" = "000" ] || fail "something still answers on $BASE"
echo "source host destroyed; $BASE no longer answers"

# ------------------------------------------------------------------------------------------------------------------
step "Target host: empty, then the restore block from backup.md"
CURRENT=nusszopf-restore-target
new_host "$CURRENT" "$NEW"
docker exec "$CURRENT" mkdir -p /opt/nusszopf-backups
docker cp "$WORK/offsite/$(basename "$BACKUP")" "$CURRENT:/opt/nusszopf-backups/"
started="$(date +%s)"
docker exec -w /root "$CURRENT" sh -eu -c "RESTORE_DIR='$BACKUP'; . /root/tools/restore.sh" > "$WORK/restore.out" 2>&1 \
    || { tail -30 "$WORK/restore.out"; fail "the Restore block failed on the empty host"; }
echo "restored and healthy $(($(date +%s) - started)) s after the first command"
grep -E "Search index rebuilt|Nusszopf $NEW" "$WORK/restore.out" | sed 's/^/  /'

step "The restored installation equals the source"
wait_for_search "$CURRENT" "$DOCS"
wait_for_idle_queue "$CURRENT"
docker cp "$WORK/before" "$CURRENT:/root/before"
on "$CURRENT" 'sh /root/tools/snapshot.sh /root/after /root/before' > /dev/null || fail "cannot snapshot the target"
docker cp "$CURRENT:/root/after" "$WORK/after"
same() { cmp -s "$WORK/before/$1" "$WORK/after/$1" || fail "$2: $(diff "$WORK/before/$1" "$WORK/after/$1" | head -6)"; }
same tables.sha256 "rows differ from the source (every column of every data table)"
same storage.sha256 "stored files differ from the source"
same search-docs.jsonl "the rebuilt search index differs from the source's"
same search-settings.json "the search index settings differ from the source's"
same migrate-status.txt "the migration status differs from the source's"
echo "identical to the source: $(cat "$WORK/after/rowcounts.txt" | tail -1 | awk '{print $1}') rows in $(ls "$WORK/after/tables" | grep -c tsv) tables, $(wc -l < "$WORK/after/storage.sha256") stored files, $DOCS search documents and the index settings, the migration status"

step "The restored installation works"
wait_for_health "$CURRENT"
grep -q "Nusszopf $NEW" "$WORK/health.txt" || fail "the target does not run $NEW"
echo "nusszopf:health: every check ok, $NEW"
on "$CURRENT" 'docker compose ps --format "{{.Service}} {{.Status}}"' | sed 's/^/  /'
on "$CURRENT" 'docker compose ps --format "{{.Service}} {{.Status}}"' | grep -v "locationiq-stub" | grep -vq "(healthy)" && fail "a service is not healthy"
on "$CURRENT" 'docker compose exec -T php-fpm php artisan schedule:list' | grep -q "newsletter:purge-unconfirmed" || fail "the scheduler has no tasks"
# Sessions live in Redis, which is not backed up (docs/handbuch/backup.md): everybody signs in again.
if in_browser_image node tests/Upgrade/session.mjs check "$BASE" p9user05 /work/session.json > "$WORK/session.out" 2>&1; then
    fail "a session from the source is still signed in, although Redis is not part of the backup"
fi
echo "the browser signed in on the source is signed out, as documented"
in_browser_image node tests/Upgrade/journeys.mjs "$BASE" "$(link "d.get('password_reset', {}).get('url')" | sed 's/^$/-/')" \
    || fail "a journey on the restored data failed"
verify="$(link "d.get('verify', {}).get('url')")"
curl -s -o /dev/null "$verify"
[ "$(q "$CURRENT" "select email_verified_at is not null from users where name = 'p9user03'")" = "t" ] || fail "the verification link mailed before the backup did not verify"
subscribe="$(link "d['newsletter_links'].get('subscribe')")"
[ "$(curl -s -o /dev/null -w '%{http_code}' "$subscribe")" = "200" ] || fail "the newsletter confirmation link mailed before the backup failed"
[ "$(q "$CURRENT" "select confirmed_at is not null from leads where email = 'p9user04@example.test'")" = "t" ] || fail "the newsletter confirmation link did not confirm"
echo "OK   links mailed before the backup: e-mail verification and newsletter confirmation"
curl -s "$BASE/legalNotice" | grep -q "Restore-Test-Impressum" || fail "the legal pages do not come from the restored legal/"
echo "OK   the legal pages come from the restored legal/"
on "$CURRENT" "docker compose exec -T php-fpm sh -c 'echo restored > storage/app/public/avatars/restore-test.txt'" || fail "php-fpm cannot write to the restored storage volume"
[ "$(curl -s "$BASE/storage/avatars/restore-test.txt")" = "restored" ] || fail "web does not serve a file written after the restore"
echo "OK   php-fpm writes to the restored storage volume and web serves the file"
# The queue and the mail relay from the restored compose.override.yaml: a password-reset mail goes out.
on "$CURRENT" "docker compose exec -T php-fpm php artisan tinker --execute \"Illuminate\\\\Support\\\\Facades\\\\Password::sendResetLink(['email' => 'p9user12@example.test']);\"" >/dev/null
i=0
until curl -s "http://127.0.0.1:$MAILPIT_PORT/api/v1/messages?limit=50" | grep -q "p9user12@example.test"; do
    i=$((i + 1)); [ "$i" -le 30 ] || fail "no mail went out through the queue after the restore"; sleep 2
done
echo "OK   a mail queued after the restore was sent (queue worker, SMTP relay from the restored compose.override.yaml)"
[ "$(q "$CURRENT" "select count(*) from failed_jobs")" = "$FAILED_SOURCE" ] || fail "jobs failed after the restore: $(on "$CURRENT" 'docker compose exec -T php-fpm php artisan queue:failed')"
echo "OK   no job failed after the restore ($FAILED_SOURCE failed jobs, as on the source)"

if [ "$SUITE" -eq 1 ]; then
    step "The Playwright suite (Chromium) on the restored installation"
    on "$CURRENT" 'docker compose exec -T php-fpm php artisan cache:clear' >/dev/null
    docker run --rm --network host --ipc host \
        -v "$ROOT:/var/www" -v nusszopf-prod-e2e-node-modules:/var/www/node_modules -w /var/www \
        -e PLAYWRIGHT_BASE_URL="$BASE" -e E2E_MAILPIT_URL="http://127.0.0.1:$MAILPIT_PORT" -e E2E_SEARCH_PAGE_SIZE=5 \
        "$PLAYWRIGHT_IMAGE" sh -c "npm ci --no-audit --no-fund >/dev/null && npx playwright test --reporter=line --retries=2 --workers=4 \
            --output=test-results/restore --project=chromium --grep-invert 'aria snapshots'; \
            status=\$?; chown -R $(id -u):$(id -g) test-results playwright-report 2>/dev/null; exit \$status" \
        || fail "the Playwright suite failed on the restored installation"
fi
docker rm -f -v "$CURRENT" >/dev/null

# ------------------------------------------------------------------------------------------------------------------
[ -n "$ROLLBACK_FROM" ] || exit 0

step "Rollback: build $ROLLBACK_FROM, install it, fill it"
git -C "$ROOT" worktree add --detach "$WORK/from" "$ROLLBACK_FROM" >/dev/null 2>&1 || fail "cannot check out $ROLLBACK_FROM"
build "$WORK/from" "$OLD"
release_files "$WORK/from" "$OLD" "$WORK/release-old"
CURRENT=nusszopf-restore-rollback
new_host "$CURRENT" "$OLD" "$NEW"
install_release "$CURRENT" "$WORK/release-old" "$OLD"
seed "$CURRENT" "$WORK/seed-old.json"
on "$CURRENT" 'sh /root/tools/snapshot.sh /root/pre-upgrade' > /dev/null
tables() { q "$CURRENT" "select string_agg(table_name, ' ' order by table_name) from information_schema.tables where table_schema = 'public'"; }
TABLES_BEFORE="$(tables)"
MIGRATIONS_BEFORE="$(q "$CURRENT" "select count(*) from migrations")"

step "Rollback: Upgrades step 2 (the backup script), the upgrade, data written after it"
docker cp "$WORK/nusszopf-backup.sh" "$CURRENT:/usr/local/bin/nusszopf-backup.sh"
PRE="$(docker exec "$CURRENT" sh -c 'chmod 700 /usr/local/bin/nusszopf-backup.sh && /usr/local/bin/nusszopf-backup.sh' | sed -n 's/^Backup written to //p')"
[ -n "$PRE" ] || fail "the pre-upgrade backup failed"
docker cp "$WORK/release-new" "$CURRENT:/release-new"
on "$CURRENT" "NUSSZOPF_BASE_URL=file:///release-new sh /release-new/install.sh --upgrade '$NEW' >/dev/null
    docker compose up -d --wait --wait-timeout 420 >/dev/null 2>&1" || fail "the upgrade to $NEW failed"
on "$CURRENT" 'docker compose exec -T php-fpm php artisan nusszopf:health' | grep -q "Nusszopf $NEW" || fail "the upgrade does not run $NEW"
q "$CURRENT" "insert into leads (id, email, name, source, consent_version, requested_at) values (gen_random_uuid(), 'after-upgrade@example.test', 'Nach dem Upgrade', 'form', '1', now())" >/dev/null
q "$CURRENT" "update users set email = 'after-upgrade-user@example.test' where name = 'p9user07'" >/dev/null
echo "upgraded to $NEW: $(tables | wc -w) tables (before: $(echo "$TABLES_BEFORE" | wc -w)), $(q "$CURRENT" "select count(*) from migrations") migrations (before: $MIGRATIONS_BEFORE); wrote a newsletter subscriber and changed an address"

step "Rollback: deployment.md \"Zurückgehen\" = docker compose down, then the restore block of backup.md"
on "$CURRENT" 'docker compose down' >/dev/null 2>&1
docker exec -w /root "$CURRENT" sh -eu -c "RESTORE_DIR='$PRE'; . /root/tools/restore.sh" > "$WORK/rollback.out" 2>&1 \
    || { tail -30 "$WORK/rollback.out"; fail "the Restore block failed as a rollback"; }
wait_for_health "$CURRENT"
grep -q "Nusszopf $OLD" "$WORK/health.txt" || fail "the rollback does not run $OLD: $(head -1 "$WORK/health.txt")"
[ "$(tables)" = "$TABLES_BEFORE" ] || fail "the rollback left other tables than before the upgrade: $(tables)"
[ "$(q "$CURRENT" "select count(*) from migrations")" = "$MIGRATIONS_BEFORE" ] || fail "the rollback left other migrations"
wait_for_idle_queue "$CURRENT"
on "$CURRENT" 'sh /root/tools/snapshot.sh /root/rolled-back /root/pre-upgrade' > /dev/null
for f in tables.sha256 storage.sha256 migrate-status.txt; do
    docker exec "$CURRENT" cmp -s "/root/pre-upgrade/$f" "/root/rolled-back/$f" || fail "after the rollback, $f differs from before the upgrade"
done
[ "$(q "$CURRENT" "select count(*) from users where email like 'after-upgrade%'")" = "0" ] || fail "data written after the upgrade survived the rollback"
echo "back on $OLD: the tables, rows, stored files and migrations of the pre-upgrade backup, nothing written after the upgrade"

step "Rollback: upgrading again works"
on "$CURRENT" "NUSSZOPF_BASE_URL=file:///release-new sh /release-new/install.sh --upgrade '$NEW' >/dev/null
    docker compose up -d --wait --wait-timeout 420 >/dev/null 2>&1" || fail "upgrading again after the rollback failed (P10-03)"
wait_for_health "$CURRENT"
grep -q "Nusszopf $NEW" "$WORK/health.txt" || fail "the second upgrade does not run $NEW"
on "$CURRENT" 'docker compose exec -T php-fpm php artisan migrate:status' | grep -q "Pending" && fail "migrations are pending after the second upgrade"
echo "upgraded again to $NEW: healthy, no migration pending"
