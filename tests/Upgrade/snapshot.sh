#!/bin/sh
# Upgrade test (scripts/upgrade-test.sh): records an installation's persisted state so that the state before and
# after an upgrade can be compared. Run in the installation's directory (where `docker compose` finds it).
#
#   sh snapshot.sh <out-dir>                  # before: every column of every data table
#   sh snapshot.sh <out-dir> <before-dir>     # after: the same columns as before, so new columns do not count
#
# Writes one deterministic dump per table (sorted by every column), the hashes of every file in the storage
# volume, the search index's documents and settings, and the migration status. Framework tables whose content
# is expected to change (cache, jobs, sessions, migrations, failed jobs) are left out.
set -eu
OUT="$1"
BEFORE="${2:-}"
mkdir -p "$OUT/tables"

q() { docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "$0"' "$1"; }

for t in $(q "select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'
              and table_name not in ('cache', 'cache_locks', 'jobs', 'job_batches', 'sessions', 'migrations', 'failed_jobs') order by 1"); do
    if [ -n "$BEFORE" ]; then
        [ -f "$BEFORE/tables/$t.cols" ] || continue
        cols="$(cat "$BEFORE/tables/$t.cols")"
    else
        cols="$(q "select string_agg(quote_ident(column_name), ',' order by ordinal_position) from information_schema.columns where table_schema = 'public' and table_name = '$t'")"
    fi
    echo "$cols" > "$OUT/tables/$t.cols"
    order="$(seq -s, 1 "$(echo "$cols" | tr ',' '\n' | wc -l)")"
    q "copy (select $cols from $t order by $order) to stdout" > "$OUT/tables/$t.tsv"
done
(cd "$OUT/tables" && wc -l ./*.tsv > ../rowcounts.txt && sha256sum ./*.tsv > ../tables.sha256)

docker compose exec -T php-fpm sh -c 'cd /var/www/storage/app && find . -type f | sort | xargs -r sha256sum' > "$OUT/storage.sha256"

meili() { docker compose exec -T php-fpm sh -c 'curl -s -H "Authorization: Bearer $MEILISEARCH_KEY" "$MEILISEARCH_HOST$0"' "$1"; }
meili "/indexes/items/documents?limit=100000" \
    | python3 -c 'import json, sys; d = json.load(sys.stdin)["results"]; d.sort(key=lambda x: x["id"]); [print(json.dumps(x, sort_keys=True, ensure_ascii=False)) for x in d]' \
    > "$OUT/search-docs.jsonl"
meili "/indexes/items/settings" | python3 -m json.tool --sort-keys > "$OUT/search-settings.json"

docker compose exec -T php-fpm php artisan migrate:status > "$OUT/migrate-status.txt" 2>&1 || true

echo "snapshot $OUT: $(wc -l < "$OUT/search-docs.jsonl") search documents, $(wc -l < "$OUT/storage.sha256") stored files"
cat "$OUT/rowcounts.txt"
