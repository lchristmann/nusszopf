#!/bin/sh
# P-11 (docs/release/parity/P-11-search-recovery.md): the search-index recovery drill. The search index is derived
# from PostgreSQL; losing it must be repaired by the documented procedure (docs/handbuch/betrieb.md, "Suchindex
# index recovery"), without a database restore and without knowledge that is not written down.
#
#   1. A new Docker host (docker:dind) with this working copy's release, installed with install.sh and filled with
#      tests/Upgrade/seed.php: public and private projects, requests in every category.
#   2. The reference: every search answer (tests/SearchRecovery/probe.php: queries x filters x "Mehr laden" pages,
#      through the search page's own query side), the page in a real browser (tests/SearchRecovery/browser.mjs), and
#      the index's documents and settings (tests/Upgrade/snapshot.sh).
#   3. Four ways to lose the index, each followed by the betrieb.md block as printed, and a comparison with the
#      reference:
#        a. the meilisearch-data volume is deleted: Meilisearch starts empty, with no index at all;
#        b. the same, but visitors keep writing before the operator notices: live indexing creates the index with
#           Meilisearch's default settings (no category filter);
#        c. the index is damaged: settings reset, documents missing, stale and altered ones, a private project;
#        d. the data on disk is corrupt: Meilisearch cannot open it.
#   4. After recovery, normal indexing of new changes (create, edit, unpublish, publish, requests, delete).
#
#   sh scripts/search-recovery-test.sh
#
# Environment: SEARCH_RECOVERY_PORT (default 18111), SEARCH_RECOVERY_KEEP=1 to keep the host.
# RELEASE_TAG=<tag> (P-16): run the drill on the images published to GHCR under that tag, pulled instead of built; the
# working copy must then be a checkout of that tag, because its compose file, installer and template are the release's.
# Needs Docker (a privileged container for docker:dind), git and python3. The Nusszopf images are built locally and
# loaded into the host, standing in for the GHCR pull.
set -eu

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
[ $# -eq 0 ] || { echo "Usage: sh scripts/search-recovery-test.sh" >&2; exit 2; }
VERSION="${RELEASE_TAG:-search-recovery}"
PORT="${SEARCH_RECOVERY_PORT:-18111}"
BASE="http://127.0.0.1:$PORT"
WORK="$(mktemp -d)"
DIND_IMAGE="docker:28-dind"
PLAYWRIGHT_IMAGE="mcr.microsoft.com/playwright:v1.63.0-noble"
HOST="nusszopf-search-recovery"

cleanup() {
    status=$?
    if [ "${SEARCH_RECOVERY_KEEP:-0}" != "1" ]; then
        docker rm -f -v "$HOST" >/dev/null 2>&1 || true
        rm -rf "$WORK"
    else
        echo "Host kept: $HOST; files in $WORK."
    fi
    [ "$status" -eq 0 ] && echo "SEARCH RECOVERY TEST PASSED" || echo "SEARCH RECOVERY TEST FAILED"
    exit "$status"
}
trap cleanup EXIT

step() { printf '\n== %s\n' "$1"; }
fail() { echo "FAIL: $1" >&2; on 'docker compose logs --tail 30 php-fpm queue-worker meilisearch 2>&1 | tail -60' || true; exit 1; }
ok() { echo "OK   $1"; }
# on <script>: runs a shell script as root in the host's installation directory.
on() { docker exec -i -w /opt/nusszopf "$HOST" sh -eu -c "$1"; }
q() { on "docker compose exec -T postgres sh -c 'psql -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\" -Atc \"\$0\"' \"$1\""; }
# meili <method> <path> [json]: the Meilisearch API, with the key from .env, from inside php-fpm.
meili() {
    on "docker compose exec -T php-fpm sh -c 'curl -s -X \"\$0\" -H \"Authorization: Bearer \$MEILISEARCH_KEY\" -H \"Content-Type: application/json\" \"\$MEILISEARCH_HOST\$1\" \${2:+--data-binary \"\$2\"}' '$1' '$2' '${3:-}'"
}
json() { python3 -c "import json, sys; d = json.load(sys.stdin); print($1)"; }
tinker() { on "docker compose exec -T php-fpm php artisan tinker --execute '$1'" > "$WORK/tinker.out" 2>&1 || fail "tinker: $(tail -5 "$WORK/tinker.out")"; }

# The code block after the "scripts/search-recovery-test.sh runs this block" marker in docs/handbuch/betrieb.md, as printed.
doc_block() {
    awk -v n="$1" '
        /<!-- P-11: scripts\/search-recovery-test.sh runs this block/ { c++; if (c == n) found = 1; next }
        found && /^```/ { if (inside) exit; inside = 1; next }
        found && inside' "$ROOT/docs/handbuch/betrieb.md"
}

wait_for_idle_queue() {
    i=0
    until [ "$(on 'docker compose exec -T redis sh -c "redis-cli --scan --pattern \"*queues:*\" | xargs -r -n1 redis-cli llen" | awk "{s+=\$1} END {print s+0}"')" = "0" ]; do
        i=$((i + 1)); [ "$i" -le 90 ] || fail "the queue did not drain"; sleep 2
    done
}

# wait_for_index: the queue is empty and Meilisearch has processed every task it was given.
wait_for_index() {
    wait_for_idle_queue
    i=0
    until [ "$(meili GET '/tasks?statuses=enqueued,processing&limit=1' | json 'd["total"]' 2>/dev/null)" = "0" ]; do
        i=$((i + 1)); [ "$i" -le 90 ] || fail "Meilisearch did not finish its tasks"; sleep 2
    done
}

probe() {
    on "docker compose exec -T php-fpm php /tmp/probe.php 2>/dev/null" > "$WORK/$1.json" || fail "the probe failed ($1)"
}

browse() {
    docker run --rm --network host --ipc host \
        -v "$ROOT:/var/www" -v nusszopf-prod-e2e-node-modules:/var/www/node_modules -w /var/www \
        "$PLAYWRIGHT_IMAGE" sh -c "[ -d node_modules/@playwright/test ] || npm ci --no-audit --no-fund >/dev/null; node tests/SearchRecovery/browser.mjs '$BASE'" \
        > "$WORK/$1.browser.json" || fail "the browser recording failed ($1)"
}

snapshot() {
    on "sh /root/tools/snapshot.sh /root/$1" > /dev/null || fail "cannot snapshot ($1)"
    rm -rf "$WORK/$1"; docker cp "$HOST:/root/$1" "$WORK/$1"
}

# same_as_reference <name>: every recorded answer, the browser's page and the index's documents and settings are
# exactly those of the reference.
same_as_reference() {
    for f in "$1.json" "$1.browser.json" "$1/search-docs.jsonl" "$1/search-settings.json"; do
        ref="$(echo "$f" | sed "s|^$1|reference|")"
        cmp -s "$WORK/$ref" "$WORK/$f" || fail "$1: $f differs from the reference: $(diff "$WORK/$ref" "$WORK/$f" | head -20)"
    done
    ok "$1: identical to the reference: $(grep -c '"page_failed"' "$WORK/$1.json") answers, the page for $(json 'len(d)' < "$WORK/$1.browser.json") links, $(wc -l < "$WORK/$1/search-docs.jsonl") documents, the index settings"
}

# broken <name>: the probe proves the loss (called right after destroying the index, before recovering).
report_broken() {
    probe "$1"
    python3 - "$WORK/$1.json" <<'PY'
import json, sys
d = json.load(open(sys.argv[1]))
a = d["answers"].values()
print(f"     index: {'exists, ' + str(d['index']['documents']) + ' documents' if d['index']['exists'] else d['index']['error']}")
print(f"     indexes on the server: {d['all_indexes']}; missing documents: {d['missing_from_index']} of {d['expected_documents']}; not belonging: {d['not_belonging_in_index']}")
print(f"     answers: {sum(1 for x in a if x['page_failed'])} fail (the page shows 'Verzopft…'), {sum(1 for x in a if not x['page_failed'] and not x.get('cards'))} empty, {sum(1 for x in a if x.get('cards'))} with cards, of {len(d['answers'])}")
PY
}

# The documents the index ought to hold, from PostgreSQL: one per public project without requests, one per request of
# a public project (docs/search/README.md, "Third slice").
expected_documents() {
    q "select (select count(*) from projects p where visibility = 'public' and not exists (select 1 from project_requests r where r.project_id = p.id))
             + (select count(*) from project_requests r join projects p on p.id = r.project_id where p.visibility = 'public')"
}

# recover <name> <block>: the n-th marked block of betrieb.md, "Suchindex", as printed; then wait until
# the queue worker has written every document.
recover() {
    started="$(date +%s)"
    docker exec -w /opt/nusszopf "$HOST" sh -eu -c "$(doc_block "$2")" > "$WORK/recover-$1.out" 2>&1 \
        || { cat "$WORK/recover-$1.out"; fail "the documented recovery failed ($1)"; }
    grep -v "^ *$" "$WORK/recover-$1.out" | grep -v "Container \|Volume \|Network " | sed 's/^/     /'
    grep -q "Index settings applied." "$WORK/recover-$1.out" || fail "$1: search:reindex did not confirm the index settings"
    want="$(expected_documents)"
    i=0
    until [ "$(meili GET /indexes/items/stats | json 'd.get("numberOfDocuments", 0)' 2>/dev/null)" = "$want" ]; do
        i=$((i + 1)); [ "$i" -le 150 ] || fail "$1: the index did not reach the $want documents PostgreSQL holds"; sleep 1
    done
    wait_for_index
    echo "     recovered: $want documents, the command's first line to the last document written in $(($(date +%s) - started)) s"
    health "$1-recovered" || fail "$1: nusszopf:health still fails after the recovery: $(cat "$WORK/$1-recovered-health.txt")"
    echo "     nusszopf:health: $(grep '| search' "$WORK/$1-recovered-health.txt" | tr -s ' ')"
}

# health <name>: nusszopf:health, kept in $WORK/<name>-health.txt; its exit status. The heartbeats may lag a few
# seconds behind a restarted service, so a failure is asked again for up to a minute.
health() {
    i=0
    until on 'docker compose exec -T php-fpm php artisan nusszopf:health' > "$WORK/$1-health.txt" 2>&1; do
        grep '| search' "$WORK/$1-health.txt" | grep -q FAILED && return 1
        i=$((i + 1)); [ "$i" -le 20 ] || return 1; sleep 3
    done
}

wipe_volume() {
    on 'docker compose rm --stop --force meilisearch >/dev/null 2>&1
        docker volume rm nusszopf_meilisearch-data >/dev/null
        docker compose up -d --wait meilisearch >/dev/null 2>&1'
    [ "$(meili GET /indexes | json 'd["total"]')" = "0" ] || fail "Meilisearch still has an index after its volume was deleted"
}

doc_block 1 | grep -q "search:reindex" || fail "betrieb.md has no marked search recovery block"
doc_block 2 | grep -q "meilisearch-data" || fail "betrieb.md has no marked block for a Meilisearch that does not start"
echo "The documented recovery (betrieb.md, \"Suchindex\"):"
doc_block 1 | sed 's/^/     /'
echo "and, if Meilisearch itself does not start:"
doc_block 2 | sed 's/^/     /'

if [ -n "${RELEASE_TAG:-}" ]; then
    step "Pull the published release $RELEASE_TAG from GHCR ($(uname -m))"
    docker pull -q "ghcr.io/lchristmann/nusszopf-php-fpm:$VERSION" >/dev/null
    docker pull -q "ghcr.io/lchristmann/nusszopf-web:$VERSION" >/dev/null
else
    step "Build this working copy's release"
    docker build -q -f "$ROOT/docker/php/Dockerfile" --target php-fpm --build-arg NUSSZOPF_VERSION="$VERSION" \
        -t "ghcr.io/lchristmann/nusszopf-php-fpm:$VERSION" "$ROOT" >/dev/null
    docker build -q -f "$ROOT/docker/php/Dockerfile" --target nginx --build-arg NUSSZOPF_VERSION="$VERSION" \
        -t "ghcr.io/lchristmann/nusszopf-web:$VERSION" "$ROOT" >/dev/null
fi
mkdir -p "$WORK/release"
cp "$ROOT/docker-compose.yaml" "$ROOT/scripts/install.sh" "$WORK/release/"
sed "s|^NUSSZOPF_VERSION=.*|NUSSZOPF_VERSION=$VERSION|" "$ROOT/.env.production.example" > "$WORK/release/env.production.example"

step "A new host: install, fill"
docker rm -f -v "$HOST" >/dev/null 2>&1 || true
docker run -d --privileged --name "$HOST" -p "127.0.0.1:$PORT:8080" "$DIND_IMAGE" >/dev/null
i=0
until docker exec "$HOST" docker info >/dev/null 2>&1; do
    i=$((i + 1)); [ "$i" -le 60 ] || fail "the Docker daemon of $HOST did not start"; sleep 1
done
docker exec "$HOST" apk add -q curl openssl python3 >/dev/null
docker save "ghcr.io/lchristmann/nusszopf-php-fpm:$VERSION" "ghcr.io/lchristmann/nusszopf-web:$VERSION" | docker exec -i "$HOST" docker load >/dev/null
docker exec "$HOST" mkdir -p /opt/nusszopf /root/tools
docker cp "$ROOT/tests/Upgrade/snapshot.sh" "$HOST:/root/tools/snapshot.sh"
docker cp "$WORK/release" "$HOST:/release"
docker cp "$ROOT/docker/stubs/locationiq.conf" "$HOST:/opt/nusszopf/locationiq.conf"
on "
    NUSSZOPF_BASE_URL=file:///release sh /release/install.sh '$BASE' '$VERSION' >/dev/null
    s() { sed -i \"s|^\$1=.*|\$1=\$2|\" .env; }
    grep -q '^MAIL_FROM_ADDRESS=\$' .env && s MAIL_FROM_ADDRESS search-recovery@example.test
    s MAIL_HOST mailpit; s MAIL_PORT 1025; s SESSION_SECURE_COOKIE false
    cat > compose.override.yaml <<'YAML'
services:
  mailpit:
    image: axllent/mailpit:latest
YAML
    docker compose pull --ignore-pull-failures --quiet >/dev/null 2>&1 || true
    docker compose up -d --wait --wait-timeout 420 >/dev/null 2>&1
" || fail "the release did not install and become healthy"
on "docker compose exec -T php-fpm sh -c 'cat > /tmp/seed.php'" < "$ROOT/tests/Upgrade/seed.php"
on "docker compose exec -T php-fpm php /tmp/seed.php" > "$WORK/seed.json" || fail "seed.php failed"
on "docker compose exec -T php-fpm sh -c 'cat > /tmp/probe.php'" < "$ROOT/tests/SearchRecovery/probe.php"
wait_for_index
echo "seeded: $(json 'd["counts"]' < "$WORK/seed.json")"
echo "public projects: $(q "select count(*) from projects where visibility = 'public'"), private: $(q "select count(*) from projects where visibility = 'private'"); requests: $(q "select string_agg(category || ' ' || n, ', ' order by category) from (select category, count(*) n from project_requests group by 1) c")"

# ------------------------------------------------------------------------------------------------------------------
step "The reference: search on the working installation (index filled by live indexing)"
probe reference
browse reference
snapshot reference
python3 - "$WORK/reference.json" "$WORK/reference.browser.json" <<'PY' || fail "the reference is not a working search"
import json, sys
d = json.load(open(sys.argv[1])); b = json.load(open(sys.argv[2]))
a = d["answers"]
def get(q, f, p=1): return next(v for k, v in a.items() if json.loads(k) == [q, f, p])
assert d["index_matches_database"], "the live index does not match the database"
assert d["private"]["projects"] > 0 and d["private"]["requests"] > 0, "the dataset has no private project with requests"
assert not d["private"]["leaks"], d["private"]["leaks"]
assert not any(v["page_failed"] for v in a.values()), "a reference answer failed"
everything = [get("", "none checked", p) for p in (1, 2, 3, 4) if any(json.loads(k) == ["", "none checked", p] for k in a)]
assert len(everything) >= 3 and everything[-1]["load_more"] is False and all(e["load_more"] for e in everything[:-1]), "no paging to test"
for f in ("companions", "rooms", "materials", "financials", "others", "none (no requests)"):
    assert get("", f)["cards"], f"the {f} filter finds nothing"
assert get("Werkzeug", "none checked")["cards"] and get("Gemeinschaftgarten", "none checked")["cards"]
assert get("Zürich", "none checked")["cards"] and not get("Zzqxwv", "none checked")["cards"]
assert not get("Berlin", "none checked")["cards"], "the place of only private projects finds something"
assert "P9TOKEN010" in get("P9TOKEN010", "none checked")["cards"][0]["title"], "the exact token is not the first card"
assert all("P9TOKEN001 " not in c["title"] for c in b["/search?q=P9TOKEN001"]["cards"]), "the page shows the private project"
assert b["/search"]["load_more_clicks"] >= 1 and b["/search?q=Berlin&f[0]=companions&f[1]=none"]["no_hits"]
print(f"     {d['index']['documents']} documents  in 'items' (primary key {d['index']['primary_key']}), = {d['expected_documents']} expected from PostgreSQL")
print(f"     {len(a)} answers recorded (13 queries x 9 filters x pages); the unfiltered search pages {[len(e['raw_ids']) for e in everything]} documents, {[len(e['cards']) for e in everything]} cards")
print(f"     browser: '/search' shows {b['/search']['cards_after_each_page']} cards after {b['/search']['load_more_clicks']} clicks on 'Mehr laden'")
print(f"     filterable {d['index']['settings']['filterableAttributes']}, sortable {d['index']['settings']['sortableAttributes']}, ranking {d['index']['settings']['rankingRules']}, maxTotalHits {d['index']['settings']['pagination']['maxTotalHits']}")
print(f"     private: {d['private']['projects']} projects, {d['private']['requests']} requests; none in the index or in any answer")
PY
cp "$WORK/reference/search-docs.jsonl" "$WORK/reference-docs.jsonl"

# ------------------------------------------------------------------------------------------------------------------
step "a. Lost: the meilisearch-data volume is deleted, Meilisearch starts empty"
wipe_volume
echo "     volume nusszopf_meilisearch-data deleted and recreated; GET /indexes: $(meili GET /indexes | json 'd')"
report_broken a-broken
python3 -c 'import json, sys; d = json.load(open(sys.argv[1])); assert not d["index"]["exists"] and all(v["page_failed"] for v in d["answers"].values())' "$WORK/a-broken.json" \
    || fail "the search still answers after the index was deleted"
browse a-broken
python3 -c 'import json, sys; d = json.load(open(sys.argv[1])); assert all(v["no_hits"] and not v["cards"] for v in d.values())' "$WORK/a-broken.browser.json" \
    || fail "the search page still shows cards after the index was deleted"
ok "the index is gone: no index on the server, every answer fails, the page shows 'Verzopft…' for every link"
health a-broken && fail "nusszopf:health passes although the index is gone"
echo "     nusszopf:health meanwhile: $(grep '| search' "$WORK/a-broken-health.txt" | tr -s ' ')"
recover a 1
probe a; browse a; snapshot a
same_as_reference a

# ------------------------------------------------------------------------------------------------------------------
step "b. Lost while the site is in use: a project is saved before the operator notices"
wipe_volume
tinker '$u = App\Models\User::where("name", "p9user02")->first(); $p = new App\Models\Project; $p->forceFill(["user_id" => $u->id, "title" => "Nach dem Verlust gespeichert", "goal" => "Ziel", "description" => "Waehrend der Index fehlte", "location" => ["remote" => true, "searchTerm" => "", "data" => (object) []], "period" => ["flexible" => true, "from" => "", "to" => ""], "visibility" => "public", "contact" => App\Models\Project::NUSSZOPF_CONTACT])->save();' >/dev/null
wait_for_index
report_broken b-broken
python3 - "$WORK/b-broken.json" <<'PY' || fail "b: the damage is not what the drill expects"
import json, sys
d = json.load(open(sys.argv[1]))
s = d["index"]["settings"]
filtered = [v for k, v in d["answers"].items() if json.loads(k)[1] not in ("none checked", "all checked")]
print(f"     live indexing created the index by itself: {d['index']['documents']} document, filterable {s['filterableAttributes']}, ranking {s['rankingRules']}")
assert d["index"]["exists"] and d["index"]["documents"] == 1 and s["filterableAttributes"] == []
assert all(v["page_failed"] for v in filtered), "a category filter works without the index settings"
print(f"     every category filter fails ({len(filtered)} answers): {filtered[0]['error'].splitlines()[0][:120]}")
PY
health b-broken && fail "nusszopf:health passes although the index lacks its settings"
echo "     nusszopf:health meanwhile: $(grep '| search' "$WORK/b-broken-health.txt" | tr -s ' ' | cut -c1-200)"
recover b 1
probe b-recovered
python3 -c 'import json, sys; d = json.load(open(sys.argv[1])); assert d["index_matches_database"] and d["index"]["settings"]["filterableAttributes"] == ["req_type", "updated_at"] and not d["private"]["leaks"]' "$WORK/b-recovered.json" \
    || fail "b: the recovered index does not match the database or lacks the settings"
[ "$(meili POST /indexes/items/search '{"q":"Nach dem Verlust gespeichert","filter":"req_type = none"}' | json 'd["hits"][0]["title"]')" = "Nach dem Verlust gespeichert" ] \
    || fail "b: the project saved during the loss is not found with the filter"
ok "b: the project saved while the index was missing is in the recovered index, and the filter works"
tinker 'App\Models\Project::where("title", "Nach dem Verlust gespeichert")->first()->delete();' >/dev/null
wait_for_index
probe b; browse b; snapshot b
same_as_reference b

# ------------------------------------------------------------------------------------------------------------------
step "c. Damaged: settings reset, documents missing, stale and altered, a private project in the index"
PRIVATE_ID="$(q "select id from projects where visibility = 'private' order by id limit 1")"
DELETED="$(python3 -c 'import json, sys; print(json.dumps([json.loads(l)["id"] for l in open(sys.argv[1])][0:40:2]))' "$WORK/reference-docs.jsonl")"
ALTERED="$(python3 -c 'import json, sys; d = json.loads(open(sys.argv[1]).readlines()[-1]); d["title"] = "Manipuliert"; d["updated_at"] = 4102444800; print(json.dumps([d]))' "$WORK/reference-docs.jsonl")"
meili DELETE /indexes/items/settings >/dev/null
meili POST /indexes/items/documents/delete-batch "$DELETED" >/dev/null
meili PUT /indexes/items/documents "$ALTERED" >/dev/null
meili POST /indexes/items/documents "[{\"id\":\"$PRIVATE_ID\",\"group_id\":\"$PRIVATE_ID\",\"req_type\":\"none\",\"title\":\"Privat, aber im Index\",\"updated_at\":4102444800},{\"id\":\"00000000-0000-0000-0000-000000000000\",\"group_id\":\"00000000-0000-0000-0000-000000000000\",\"req_type\":\"none\",\"title\":\"Geloeschtes Projekt\",\"updated_at\":4102444800}]" >/dev/null
wait_for_index
report_broken c-broken
python3 - "$WORK/c-broken.json" "$WORK/reference.json" <<'PY' || fail "c: the damage is not what the drill expects"
import json, sys
d = json.load(open(sys.argv[1])); r = json.load(open(sys.argv[2]))
assert d["missing_from_index"] == 20 and d["not_belonging_in_index"] == 2 and d["private"]["leaks"], "the damage did not take"
assert d["index"]["settings"]["filterableAttributes"] == [] and d["index"]["settings"]["rankingRules"][-1] != "updated_at:desc"
differs = sum(1 for k in r["answers"] if d["answers"].get(k) != r["answers"][k])
print(f"     settings at Meilisearch's defaults; the private project is in the index ({len(d['private']['leaks'])} hits, e.g. {d['private']['leaks'][0][:90]}); {differs} of {len(r['answers'])} answers differ from the reference")
PY
recover c 1
probe c; browse c; snapshot c
same_as_reference c

# ------------------------------------------------------------------------------------------------------------------
step "d. Corrupt on disk: Meilisearch cannot open its data"
on 'docker compose stop meilisearch >/dev/null 2>&1
    docker run --rm -v nusszopf_meilisearch-data:/meili_data alpine sh -c "for f in \$(find /meili_data -name data.mdb); do head -c 65536 /dev/urandom | dd of=\$f conv=notrunc 2>/dev/null; done"
    docker compose start meilisearch >/dev/null 2>&1 || true'
sleep 10
echo "     meilisearch: $(on 'docker compose ps -a --format "{{.Status}}" meilisearch')"
on 'docker compose logs --tail 3 meilisearch' 2>&1 | sed 's/^/     /' | cut -c1-200
on 'docker compose ps --format "{{.Status}}" meilisearch' | grep -q Restarting || fail "d: Meilisearch is not failing on its corrupt data"
health d-broken && fail "nusszopf:health passes although Meilisearch does not start"
echo "     nusszopf:health meanwhile: $(grep '| search' "$WORK/d-broken-health.txt" | tr -s ' ' | cut -c1-160)"
if docker exec -w /opt/nusszopf "$HOST" sh -eu -c "$(doc_block 1)" > "$WORK/d-reindex-only.out" 2>&1; then
    fail "d: search:reindex reported success against a Meilisearch that does not start"
fi
echo "     search:reindex alone fails, and says so: $(grep -m1 'failed' "$WORK/d-reindex-only.out" | cut -c1-160)"
recover d 2
probe d; browse d; snapshot d
same_as_reference d

# ------------------------------------------------------------------------------------------------------------------
step "Normal indexing after the recovery: new changes reach the index by themselves"
found() { meili POST /indexes/items/search "{\"q\":\"$1\"${2:+,\"filter\":\"$2\"}}" | json '" | ".join(sorted(h.get("req_title") or h["title"] for h in d["hits"]))'; }
tinker '$u = App\Models\User::where("name", "p9user03")->first(); $p = new App\Models\Project; $p->forceFill(["user_id" => $u->id, "title" => "Neu nach der Wiederherstellung", "goal" => "Ziel", "description" => "Frisch", "location" => ["remote" => true, "searchTerm" => "", "data" => (object) []], "period" => ["flexible" => true, "from" => "", "to" => ""], "visibility" => "public", "contact" => App\Models\Project::NUSSZOPF_CONTACT])->save();' >/dev/null
wait_for_index
[ "$(found 'Neu nach der Wiederherstellung' 'req_type = none')" = "Neu nach der Wiederherstellung" ] || fail "a new public project is not indexed"
ok "a new public project is found (filter: no requests)"
tinker '$p = App\Models\Project::where("title", "Neu nach der Wiederherstellung")->first(); $r = new App\Models\ProjectRequest; $r->forceFill(["project_id" => $p->id, "title" => "Werkbank gesucht", "category" => "materials", "description" => "Stabil", "description_template" => App\Support\RichText::fromPlainText("Stabil")])->save();' >/dev/null
wait_for_index
[ "$(found 'Neu nach der Wiederherstellung' 'req_type = materials')" = "Werkbank gesucht" ] || fail "a new request is not indexed"
[ "$(found 'Neu nach der Wiederherstellung' 'req_type = none')" = "" ] || fail "the project document stayed after its first request"
ok "its first request replaces the project's document (filter: materials)"
tinker 'App\Models\Project::where("title", "Neu nach der Wiederherstellung")->first()->update(["title" => "Umbenannt nach der Wiederherstellung"]);' >/dev/null
wait_for_index
[ "$(meili POST /indexes/items/search '{"q":"Umbenannt nach der Wiederherstellung"}' | json 'd["hits"][0]["title"]')" = "Umbenannt nach der Wiederherstellung" ] || fail "an edit is not indexed"
ok "an edited title is indexed"
tinker 'App\Models\Project::where("title", "Umbenannt nach der Wiederherstellung")->first()->update(["visibility" => "private"]);' >/dev/null
wait_for_index
[ "$(found 'Umbenannt nach der Wiederherstellung')" = "" ] || fail "a project made private stays in the index"
ok "unpublished: the project and its request are gone from the index"
tinker 'App\Models\Project::where("title", "Umbenannt nach der Wiederherstellung")->first()->update(["visibility" => "public"]);' >/dev/null
wait_for_index
[ "$(found 'Umbenannt nach der Wiederherstellung')" = "Werkbank gesucht" ] || fail "a project made public again is not indexed"
ok "published again: its request is back"
tinker 'App\Models\Project::where("title", "Umbenannt nach der Wiederherstellung")->first()->delete();' >/dev/null
wait_for_index
[ "$(found 'Umbenannt nach der Wiederherstellung')" = "" ] || fail "a deleted project stays in the index"
ok "deleted: gone from the index"
probe final; browse final; snapshot final
same_as_reference final
[ "$(q "select count(*) from failed_jobs")" = "0" ] || fail "jobs failed: $(on 'docker compose exec -T php-fpm php artisan queue:failed')"
ok "no job failed during the whole drill"
