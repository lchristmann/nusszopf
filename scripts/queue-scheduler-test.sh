#!/bin/sh
# P-12 (docs/release/parity/P-12-queue-scheduler.md): the queue and scheduler drill, on the production Compose stack.
#
#   1. A new Docker host (docker:dind) with this working copy's release, installed with install.sh and filled with
#      tests/Upgrade/seed.php, plus a Mailpit as the mail relay.
#   2. Only a stack in its documented configuration is used: the queue worker and the scheduler run exactly the
#      commands of docker-compose.yaml.
#   3. The drills, one numbered step each: 1 the configuration as shipped, 2 a mail that outlives its account, 3 the
#      worker restarted gracefully, by `queue:restart` and killed, 4 Redis restarted and killed with work queued (with
#      and without the append-only file) and down while the stack runs, 5 a failing job through all its retries and
#      `queue:retry all`, 6 `search:reindex` with the worker down, Redis down and Meilisearch lost, 7 the scheduler
#      (restarts at the minute boundary, the purge at 03:30, stopped for five minutes), 8 the worker stopped and what an
#      operator sees of each failure, 9 the state at the end.
#
#   sh scripts/queue-scheduler-test.sh
#
# Environment: QS_PORT (default 18112); QS_KEEP=1 keeps the host; QS_REUSE=1 runs against the kept host without
# building and installing again; QS_STEPS="3 5" runs only those steps (default: all). Needs Docker (a privileged
# container for docker:dind), git and python3. The Nusszopf images are built locally and loaded into the host,
# standing in for the GHCR pull. About an hour with the build: the retry policy (10 s, 30 s, 1 min, 2 min), the health checks'
# three-minute tolerance and a scheduler that is stopped for five minutes are waited out for real.
set -eu

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
[ $# -eq 0 ] || { echo "Usage: sh scripts/queue-scheduler-test.sh" >&2; exit 2; }
VERSION="queue-scheduler"
PORT="${QS_PORT:-18112}"
BASE="http://127.0.0.1:$PORT"
WORK="$(mktemp -d)"
DIND_IMAGE="docker:28-dind"
HOST="nusszopf-queue-scheduler"

cleanup() {
    status=$?
    if [ "${QS_KEEP:-0}" != "1" ]; then
        docker rm -f -v "$HOST" >/dev/null 2>&1 || true
        rm -rf "$WORK"
    else
        echo "Host kept: $HOST; files in $WORK."
    fi
    [ "$status" -eq 0 ] && echo "QUEUE/SCHEDULER TEST PASSED" || echo "QUEUE/SCHEDULER TEST FAILED"
    exit "$status"
}
trap cleanup EXIT

step() { printf '\n== %s\n' "$1"; }
fail() { echo "FAIL: $1" >&2; on 'docker compose ps 2>&1; docker compose logs --tail 25 queue-worker scheduler 2>&1 | tail -60' || true; exit 1; }
ok() { echo "OK   $1"; }
# on <script>: runs a shell script as root in the host's installation directory.
on() { docker exec -i -w /opt/nusszopf "$HOST" sh -eu -c "$1"; }
q() { on "docker compose exec -T postgres sh -c 'psql -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\" -Atc \"\$0\"' \"$1\""; }
# meili <method> <path> [json]: the Meilisearch API, with the key from .env, from inside php-fpm.
meili() {
    on "docker compose exec -T php-fpm sh -c 'curl -s -X \"\$0\" -H \"Authorization: Bearer \$MEILISEARCH_KEY\" -H \"Content-Type: application/json\" \"\$MEILISEARCH_HOST\$1\" \${2:+--data-binary \"\$2\"}' '$1' '$2' '${3:-}'"
}
json() { python3 -c "import json, sys; d = json.load(sys.stdin); print($1)"; }
failed_jobs() { q "select count(*) from failed_jobs"; }

# await <what> <seconds> <expression>: evaluates the expression again every second until it succeeds. Quote it, so that
# what it reads (a count, a state) is read on every try, not once.
await() {
    what="$1"; seconds="$2"; cmd="$3"
    i=0
    until eval "$cmd" >/dev/null 2>&1; do
        i=$((i + 1)); [ "$i" -le "$seconds" ] || fail "timed out after $seconds s: $what"; sleep 1
    done
}
now() { date +%s; }
all_containers_healthy() { [ "$(on 'docker ps --filter health=healthy -q | wc -l')" = "$(on 'docker ps -q | wc -l')" ]; }
check_failing() { ! on "docker compose exec -T php-fpm php artisan nusszopf:health --only=$1" >/dev/null 2>&1; }
marker_hits() { meili POST /indexes/items/search "{\"q\":\"$1\"}" | json 'len(d["hits"])'; }

# mailstat: "<messages in Mailpit> <distinct recipients>".
mailstat() {
    on "docker compose exec -T php-fpm curl -s 'http://mailpit:8025/api/v1/messages?limit=5000'" \
        | python3 -c 'import json, sys; m = json.load(sys.stdin)["messages"]; t = [x["To"][0]["Address"] for x in m]; print(len(t), len(set(t)))'
}
mail_total() { mailstat | cut -d' ' -f1; }
mail_unique() { mailstat | cut -d' ' -f2; }
mail_reset() { on "docker compose exec -T php-fpm curl -s -X DELETE http://mailpit:8025/api/v1/messages" >/dev/null; }
# work <what> <args...>: puts work on the queue through the application (tests/QueueScheduler/work.php).
work() { on "docker compose exec -T php-fpm php /tmp/work.php $*" | tail -1; }

# health <check>: the row of `nusszopf:health`, and /health's status code through the web container.
health_row() { on 'docker compose exec -T php-fpm php artisan nusszopf:health' 2>&1 | grep "| $1 " | tr -s ' ' || true; }
health_status() { on 'docker compose exec -T web curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1/health' 2>/dev/null || echo "-"; }
health_ok() { on 'docker compose exec -T php-fpm php artisan nusszopf:health' >/dev/null 2>&1; }
restarts() { on "docker inspect -f '{{.RestartCount}}' nusszopf-$1-1"; }
state() { on "docker inspect -f '{{.State.Status}}/{{if .State.Health}}{{.State.Health.Status}}{{end}}' nusszopf-$1-1"; }
worker_log() { on 'docker compose logs --no-log-prefix queue-worker 2>&1'; }

# The documents PostgreSQL says the index ought to hold, and the ones it holds.
expected_documents() {
    q "select (select count(*) from projects p where visibility = 'public' and not exists (select 1 from project_requests r where r.project_id = p.id))
             + (select count(*) from project_requests r join projects p on p.id = r.project_id where p.visibility = 'public')"
}
index_documents() { meili GET /indexes/items/stats | json 'd.get("numberOfDocuments", d.get("code"))' 2>/dev/null; }
snapshot() {
    on "sh /root/tools/snapshot.sh /root/$1" > /dev/null || fail "cannot snapshot ($1)"
    rm -rf "$WORK/$1"; docker cp "$HOST:/root/$1" "$WORK/$1"
}
same_index_as_reference() {
    snapshot "$1"
    for f in search-docs.jsonl search-settings.json; do
        cmp -s "$WORK/reference/$f" "$WORK/$1/$f" || fail "$1: the index's $f differs from the reference: $(diff "$WORK/reference/$f" "$WORK/$1/$f" | head -10)"
    done
    ok "$1: the index's $(wc -l < "$WORK/$1/search-docs.jsonl") documents and its settings are identical to the reference"
}
# The jobs still to run: waiting, delayed for their next attempt, or reserved by a worker.
queue_jobs() {
    on "docker compose exec -T redis sh -c 'redis-cli LLEN nusszopf-database-queues:default; redis-cli ZCARD nusszopf-database-queues:default:delayed; redis-cli ZCARD nusszopf-database-queues:default:reserved'" \
        | tr -d '\r' | awk '{s += $1} END {print s + 0}'
}
queue_idle() { [ "$(queue_jobs)" = "0" ]; }

# ------------------------------------------------------------------------------------------------------------------
setup() {
    step "Build this working copy's release, install it on a new host, fill it"
    docker build -q -f "$ROOT/docker/php/Dockerfile" --target php-fpm --build-arg NUSSZOPF_VERSION="$VERSION" \
        -t "ghcr.io/lchristmann/nusszopf-php-fpm:$VERSION" "$ROOT" >/dev/null
    docker build -q -f "$ROOT/docker/php/Dockerfile" --target nginx --build-arg NUSSZOPF_VERSION="$VERSION" \
        -t "ghcr.io/lchristmann/nusszopf-web:$VERSION" "$ROOT" >/dev/null
    mkdir -p "$WORK/release"
    cp "$ROOT/docker-compose.yaml" "$ROOT/scripts/install.sh" "$WORK/release/"
    sed "s|^NUSSZOPF_VERSION=.*|NUSSZOPF_VERSION=$VERSION|" "$ROOT/.env.production.example" > "$WORK/release/env.production.example"

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
        grep -q '^MAIL_FROM_ADDRESS=\$' .env && s MAIL_FROM_ADDRESS queue-scheduler@example.test
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
    on "docker compose exec -T php-fpm sh -c 'cat > /tmp/work.php'" < "$ROOT/tests/QueueScheduler/work.php"
    await "the queue to be idle after the seed" 90 'queue_idle'
    snapshot reference
    echo "seeded: $(json 'd["counts"]' < "$WORK/seed.json"); $(wc -l < "$WORK/reference/search-docs.jsonl") documents in the index"
}

# 1 -------------------------------------------------------------------------------------------------------------------
step_1() {
    step "1. The configuration as shipped (docker-compose.yaml, unchanged)"
    for svc in queue-worker scheduler redis; do
        echo "     $svc: restart=$(on "docker inspect -f '{{.HostConfig.RestartPolicy.Name}}' nusszopf-$svc-1") command=$(on "docker inspect -f '{{.Config.Cmd}}' nusszopf-$svc-1")"
    done
    [ "$(on "docker inspect -f '{{.Config.Cmd}}' nusszopf-queue-worker-1")" = "[php artisan queue:work --tries=5 --backoff=10,30,60,120 --max-time=3600]" ] || fail "the worker does not run the documented command"
    [ "$(on "docker inspect -f '{{.Config.Cmd}}' nusszopf-scheduler-1")" = "[php artisan schedule:work]" ] || fail "the scheduler does not run the documented command"
    [ "$(on "docker inspect -f '{{.HostConfig.RestartPolicy.Name}}' nusszopf-queue-worker-1 nusszopf-scheduler-1" | sort -u)" = "unless-stopped" ] || fail "a restart policy is not unless-stopped"
    ok "the worker and the scheduler run exactly the documented commands, restart: unless-stopped"
    [ "$(on 'docker compose exec -T redis redis-cli CONFIG GET appendonly' | tail -1 | tr -d '\r')" = "yes" ] || fail "Redis does not keep an append-only file"
    ok "Redis keeps an append-only file (P12-01)"
    on 'docker compose exec -T php-fpm php artisan schedule:list' | sed 's/^/     /'
    [ "$(on 'docker compose exec -T php-fpm php artisan schedule:list' | grep -c 'heartbeat\|purge-unconfirmed')" = "3" ] || fail "the schedule is not the two heartbeats and the purge"
    await "every check ok" 120 'health_ok'
    on 'docker compose exec -T php-fpm php artisan nusszopf:health' | sed 's/^/     /'
    [ "$(failed_jobs)" = "0" ] || fail "failed_jobs is not empty at the start"
    mail_reset
    n=30
    work mails "$n" s1- >/dev/null
    await "the worker to send $n mails" 60 'test "$(mail_total)" = "$n"'
    [ "$(mail_unique)" = "$n" ] || fail "a mail was sent twice"
    ok "the worker took $n queued mails through Redis and Mailpit received exactly $n, each once; failed_jobs: $(failed_jobs)"
}

# 2 -------------------------------------------------------------------------------------------------------------------
step_2() {
    step "2. A mail whose account is deleted before the worker sends it (P12-03)"
    mail_reset
    on 'docker compose stop queue-worker' >/dev/null 2>&1
    work gone s2 >/dev/null
    echo "     welcome mail queued, account deleted; jobs waiting: $(queue_jobs)"
    on 'docker compose start queue-worker' >/dev/null 2>&1
    await "the job to be taken" 60 'queue_idle'
    sleep 2
    on 'docker compose logs --no-log-prefix queue-worker 2>&1 | grep WelcomeMail | tail -2' | sed 's/^/     /'
    [ "$(failed_jobs)" = "0" ] || fail "the mail of a deleted account is in failed_jobs"
    [ "$(mail_total)" = "0" ] || fail "a mail was sent to a deleted account"
    ok "the job was dropped: nothing sent, nothing in failed_jobs"
}

# 3 -------------------------------------------------------------------------------------------------------------------
step_3() {
    step "3. The queue worker restarted with work waiting"
    # a. docker compose restart: SIGTERM, the worker finishes its job and exits.
    mail_reset
    on 'docker compose stop queue-worker' >/dev/null 2>&1
    work mails 300 s3a- >/dev/null
    on 'docker compose start queue-worker' >/dev/null 2>&1
    await "the worker to be sending" 30 'test "$(mail_total)" -gt 20'
    at="$(mail_total)"
    on 'docker compose restart queue-worker' >/dev/null 2>&1
    await "all 300 mails" 120 'test "$(mail_total)" = "300"'
    [ "$(mail_unique)" = "300" ] || fail "3a: a mail was sent twice: $(mailstat)"
    ok "a. docker compose restart at $at of 300 mails: all 300 delivered, each once (worker: 'Worker STOPPED Interrupted' after its current job)"

    # b. queue:restart, the way a deploy tells workers to stop; --max-time=3600 ends the worker the same way.
    before="$(restarts queue-worker)"
    on 'docker compose exec -T php-fpm php artisan queue:restart' >/dev/null
    await "Docker to restart the worker" 60 'test "$(restarts queue-worker)" -gt "$before"'
    await "the worker healthy again" 120 'test "$(state queue-worker)" = "running/healthy"'
    mail_reset
    work mails 10 s3b- >/dev/null
    await "10 mails after queue:restart" 60 'test "$(mail_total)" = "10"'
    ok "b. queue:restart ends the worker with exit code 0, Docker starts it again (restarts $before -> $(restarts queue-worker)), and it works the queue"

    # c. kill -9 in the middle of the backlog: at-least-once delivery, nothing lost.
    mail_reset
    on 'docker compose stop queue-worker' >/dev/null 2>&1
    work mails 300 s3c- >/dev/null
    on 'docker compose start queue-worker' >/dev/null 2>&1
    await "the worker to be sending" 30 'test "$(mail_total)" -gt 20'
    on 'docker compose kill queue-worker' >/dev/null 2>&1
    echo "     killed with -9 at $(mail_total) of 300 mails, $(queue_jobs) jobs left (reserved by the dead worker: $(on 'docker compose exec -T redis redis-cli ZCARD nusszopf-database-queues:default:reserved' | tr -d '\r'))"
    on 'docker compose start queue-worker' >/dev/null 2>&1 || true
    await "all 300 recipients served" 120 'test "$(mail_unique)" = "300"'
    await "the job the dead worker held to come back (retry_after 90 s)" 150 'queue_idle'
    sleep 3
    echo "     mails in Mailpit: $(mail_total), distinct recipients: $(mail_unique); failed_jobs: $(failed_jobs)"
    [ "$(mail_unique)" = "300" ] || fail "3c: a recipient never got the mail"
    [ "$(mail_total)" -le 301 ] || fail "3c: more than the one job in flight was delivered twice"
    [ "$(failed_jobs)" = "0" ] || fail "3c: a job failed"
    ok "c. kill -9: every one of the 300 recipients got the mail; at most the one job the worker was holding ran a second time, after retry_after (90 s)"
}

# 4 -------------------------------------------------------------------------------------------------------------------
# lose_redis_with_work <name>: 30 mails queued while no worker runs, Redis killed (no shutdown, no final save), started
# again, the worker started: how many mails arrive.
lose_redis_with_work() {
    mail_reset
    on 'docker compose stop queue-worker' >/dev/null 2>&1
    on 'docker compose exec -T redis redis-cli SAVE' >/dev/null
    work mails 30 "$1" >/dev/null
    queued="$(queue_jobs)"
    on 'docker compose kill redis' >/dev/null 2>&1
    on 'docker compose up -d --wait redis' >/dev/null 2>&1
    kept="$(queue_jobs)"
    on 'docker compose start queue-worker' >/dev/null 2>&1
    sleep 25
    echo "     $queued jobs queued, $kept still queued after kill -9 and a start of Redis; mails delivered: $(mail_total) of 30"
}
step_4() {
    step "4. Redis"
    # a. What it was before P12-01: a snapshot every few minutes.
    on 'cp compose.override.yaml compose.override.yaml.keep
        printf "  redis:\n    command: [\"redis-server\", \"--appendonly\", \"no\"]\n" >> compose.override.yaml
        docker compose up -d --wait redis >/dev/null 2>&1'
    echo "   Redis as it was (snapshots only), 30 mails queued right after a save:"
    lose_redis_with_work s4a-
    [ "$(mail_total)" -lt 30 ] || fail "4a: the drill does not show the loss it is meant to show"
    ok "a. without the append-only file a crash silently loses the work queued since the last snapshot (failed_jobs: $(failed_jobs))"
    on 'mv compose.override.yaml.keep compose.override.yaml; docker compose up -d --wait redis >/dev/null 2>&1'
    await "the stack healthy" 180 'health_ok'

    # b. As shipped.
    echo "   Redis as shipped (append-only file), the same:"
    lose_redis_with_work s4b-
    [ "$(mail_total)" = "30" ] && [ "$(mail_unique)" = "30" ] || fail "4b: mails were lost or doubled: $(mailstat)"
    ok "b. with the append-only file the queue survives kill -9: all 30 mails delivered once"

    # c. A shutdown with a backlog.
    mail_reset
    on 'docker compose stop queue-worker' >/dev/null 2>&1
    work mails 30 s4c- >/dev/null
    on 'docker compose restart redis >/dev/null 2>&1; docker compose start queue-worker >/dev/null 2>&1'
    await "30 mails after a Redis restart" 90 'test "$(mail_total)" = "30"'
    ok "c. docker compose restart redis with 30 mails waiting: all delivered"

    # d. Redis down while the stack runs.
    await "the stack healthy" 180 'health_ok'
    b_before="$(restarts queue-worker)"
    on 'docker compose stop redis' >/dev/null 2>&1
    sleep 60
    echo "     Redis down 60 s: /health $(health_status); worker $(state queue-worker) (restarted $(( $(restarts queue-worker) - b_before )) times); scheduler $(state scheduler)"
    [ "$(health_status)" = "503" ] || fail "4d: /health does not report Redis down"
    [ "$(restarts queue-worker)" -gt "$b_before" ] || fail "4d: the worker did not crash-loop"
    on 'docker compose logs --no-log-prefix scheduler 2>&1 | grep "production.ERROR" | tail -1' | cut -c1-160 | sed 's/^/     scheduler log: /'
    t="$(now)"
    on 'docker compose start redis' >/dev/null 2>&1
    await "the whole stack healthy again, by itself" 300 'health_ok'
    await "every container healthy" 300 'all_containers_healthy'
    ok "d. Redis down for 60 s: /health 503, the worker restarted itself in a loop, the scheduler kept running; $(($(now) - t)) s after Redis came back every check and container was healthy, with no command run"
    mail_reset; work mails 5 s4d- >/dev/null
    await "5 mails after the outage" 60 'test "$(mail_total)" = "5"'
    ok "   and the queue works again (5 of 5 mails)"
}

# 5 -------------------------------------------------------------------------------------------------------------------
step_5() {
    step "5. A job that fails: retries, backoff, failed_jobs, queue:retry all (index updates while Meilisearch is down)"
    await "the queue idle" 60 'queue_idle'
    on 'docker compose stop queue-worker' >/dev/null 2>&1
    on 'docker compose stop meilisearch' >/dev/null 2>&1
    marker="Drill5$(now)"
    work motto "$marker" >/dev/null
    since="$(date -u '+%Y-%m-%d %H:%M:%S')"
    # Not `start`: Compose would also start the stopped Meilisearch, which php-fpm, and so the worker, depends on.
    on 'docker compose up -d --no-deps queue-worker' >/dev/null 2>&1
    t0="$(now)"
    echo "     Meilisearch down, one project edited (its index jobs queued), worker started"
    await "every job to be tried five times" 420 'queue_idle'
    took=$(($(now) - t0))
    worker_log | awk -v s="$since" '$1" "$2 >= s' | grep -E "MakeSearchable|RemoveFromSearch" | sed 's/ \.\.*/ /' | sed 's/^/     /' | head -40
    failed="$(failed_jobs)"
    fails="$(worker_log | awk -v s="$since" '$1" "$2 >= s' | grep -c 'Scout.*FAIL' || true)"
    echo "     $fails failed attempts for $failed job(s); the queue was empty $took s after the first attempt"
    [ "$failed" -ge 1 ] && [ "$fails" = "$((failed * 5))" ] || fail "5: expected exactly 5 attempts for each of the jobs in failed_jobs"
    ok "each job was tried 5 times (10 s, 30 s, 1 min, 2 min apart), then kept in failed_jobs, after $took s in all"
    on 'docker compose start meilisearch' >/dev/null 2>&1
    await "Meilisearch healthy" 60 'test "$(state meilisearch)" = "running/healthy"'
    await "the search check ok" 60 '! check_failing search'
    echo "     Meilisearch is back, and nothing retries the failed jobs by itself:"
    echo "     nusszopf:health: $(health_row failed_jobs | cut -c1-220)"
    echo "     /health: $(health_status); queue-worker: $(state queue-worker) (a failed job is not a dead worker)"
    [ "$(health_status)" = "503" ] || fail "5: /health does not report the failed jobs"
    [ "$(state queue-worker)" = "running/healthy" ] || fail "5: the worker container is not healthy although it works"
    on 'docker compose exec -T php-fpm php artisan queue:retry all' | tail -2 | sed 's/^/     /'
    await "the queue idle again" 90 'queue_idle'
    await "the edit to be searchable" 60 'test "$(marker_hits "$marker")" -ge 1'
    [ "$(failed_jobs)" = "0" ] || fail "5: queue:retry all left a job in failed_jobs"
    await "health ok again" 120 'health_ok'
    ok "queue:retry all ran them again: the edit is searchable, failed_jobs is empty, /health is $(health_status) again"
    echo "     The SMTP twin of this drill (a mail through 5 attempts and queue:retry all) was run on this stack in P-7."
    # The edit is part of the data now: what the index must equal from here on is what PostgreSQL holds today.
    [ "$(index_documents)" = "$(expected_documents)" ] || fail "5: the index does not hold what PostgreSQL says"
    snapshot reference
}

# 6 -------------------------------------------------------------------------------------------------------------------
step_6() {
    step "6. search:reindex and the queue (the case P-11 deferred)"
    want="$(expected_documents)"
    await "the queue idle" 60 'queue_idle'

    echo "   a. The queue worker is not running:"
    on 'docker compose stop queue-worker' >/dev/null 2>&1
    on 'docker compose exec -T php-fpm php artisan search:reindex' > "$WORK/6a.out" 2>&1 && code=0 || code=$?
    sed 's/^/     /' "$WORK/6a.out" | grep -v "^     $"
    echo "     exit $code; documents in the index: $(index_documents) of $want; jobs waiting: $(queue_jobs)"
    [ "$code" = "0" ] && [ "$(index_documents)" = "0" ] || fail "6a: not the state the drill expects"
    grep -q "still waiting" "$WORK/6a.out" || fail "6a: the command did not warn that nothing writes the documents"
    echo "     nusszopf:health meanwhile: $(health_row queue | cut -c1-120)"
    on 'docker compose start queue-worker' >/dev/null 2>&1
    await "every document written" 90 'test "$(index_documents)" = "$want"'
    await "the queue idle" 60 'queue_idle'
    same_index_as_reference 6a

    echo "   b. Redis is down (the queue cannot take the import):"
    on 'docker compose stop redis' >/dev/null 2>&1
    on 'docker compose exec -T php-fpm php artisan search:reindex' > "$WORK/6b.out" 2>&1 && code=0 || code=$?
    sed 's/^/     /' "$WORK/6b.out" | grep -v "^     $" | cut -c1-220
    echo "     exit $code; documents in the index: $(index_documents) of $want"
    [ "$code" != "0" ] && [ "$(index_documents)" = "$want" ] || fail "6b: the command emptied the index or did not fail"
    grep -q "nothing was changed" "$WORK/6b.out" || fail "6b: the command does not say that nothing was changed"
    on 'docker compose start redis' >/dev/null 2>&1
    await "the stack healthy" 300 'health_ok'
    ok "6b: the working index was left as it was (P12-04), and the command said so"

    echo "   c. Meilisearch is lost while the documents wait in the queue:"
    on 'docker compose stop queue-worker' >/dev/null 2>&1
    on 'docker compose exec -T php-fpm php artisan search:reindex' > "$WORK/6c.out" 2>&1 || true
    on 'docker compose rm --stop --force meilisearch >/dev/null 2>&1
        docker volume rm nusszopf_meilisearch-data >/dev/null
        docker compose up -d --wait meilisearch >/dev/null 2>&1'
    echo "     the queue holds $(queue_jobs) job(s); Meilisearch is empty: $(meili GET /indexes | json 'd["total"]') indexes"
    on 'docker compose start queue-worker' >/dev/null 2>&1
    await "the queue idle" 90 'queue_idle'
    echo "     the jobs ran against the empty Meilisearch: $(index_documents) documents; nusszopf:health: $(health_row search | cut -c1-200)"
    health_ok && fail "6c: the health check does not notice an index without settings"
    echo "     the documented recovery (operations.md, \"Search index recovery\"):"
    on 'docker compose exec -T php-fpm php artisan search:reindex' | tail -2 | sed 's/^/     /'
    await "every document written" 90 'test "$(index_documents)" = "$want"'
    await "health ok" 120 'health_ok'
    same_index_as_reference 6c
}

# 7 -------------------------------------------------------------------------------------------------------------------
step_7() {
    step "7. The scheduler"
    await "the stack healthy" 180 'health_ok'

    if [ "${QS_SKIP_7A:-0}" != "1" ]; then
        echo "   a. Restarted and killed at the minute boundary, four times:"
        from="$(date -u '+%Y-%m-%d %H:%M')"
        for i in 1 2 3 4; do
            on 'while [ "$(date +%S)" != "58" ]; do sleep 0.2; done'
            if [ $((i % 2)) = 1 ]; then on 'docker compose restart scheduler' >/dev/null 2>&1; how="docker compose restart"
            else on 'docker compose kill scheduler >/dev/null 2>&1; docker compose start scheduler' >/dev/null 2>&1; how="kill -9, then start"; fi
            echo "     $(date -u +%H:%M:%S) $how"
            sleep 65
        done
        sleep 30
        on 'docker compose logs --no-log-prefix scheduler 2>&1' | grep 'Running \[' | awk -v f="$from" '$1" "$2 >= f' \
            | python3 -c '
    import collections, datetime, sys
    c = collections.Counter()
    for line in sys.stdin:
        p = line.split()
        c[(p[0] + " " + p[1][:5], p[3])] += 1
    minutes = sorted({k[0] for k in c})
    stamps = [datetime.datetime.strptime(m, "%Y-%m-%d %H:%M") for m in minutes]
    dup = [(k, v) for k, v in sorted(c.items()) if v > 1]
    gaps = [(minutes[i], minutes[i + 1]) for i in range(len(stamps) - 1) if (stamps[i + 1] - stamps[i]).seconds != 60]
    print("     %d minutes, %d runs of scheduler-heartbeat, %d of queue-heartbeat; duplicated: %s; gaps: %s" % (
        len(minutes), sum(v for k, v in c.items() if k[1] == "[scheduler-heartbeat]"), sum(v for k, v in c.items() if k[1] == "[queue-heartbeat]"), dup or "none", gaps or "none"))
    sys.exit(1 if dup or gaps or len(minutes) < 8 else 0)' || fail "7a: a run was doubled or skipped"
        ok "a. every task ran exactly once every minute across the restarts, none twice, none skipped"
    fi

    echo "   b. The purge at 03:30 UTC, through the scheduler itself (the clock is set, the rest is the production image):"
    work leads 2 drill7 >/dev/null
    on "docker compose exec -T php-fpm sh -c 'cat > /tmp/at.php'" < "$ROOT/tests/QueueScheduler/at.php"
    [ "$(q "select count(*) from leads where email like 'drill7-%'")" = "2" ] || fail "7b: the stale subscriptions were not created"
    on 'docker compose exec -T php-fpm php /tmp/at.php "03:29:00"' | sed 's/^/     /'
    [ "$(q "select count(*) from leads where email like 'drill7-%'")" = "2" ] || fail "7b: the purge ran at 03:29"
    on 'docker compose exec -T php-fpm php /tmp/at.php "03:30:00"' | sed 's/^/     /'
    [ "$(q "select count(*) from leads where email like 'drill7-%'")" = "0" ] || fail "7b: the purge did not run at 03:30"
    leads="$(q "select count(*) from leads")"
    on 'docker compose exec -T php-fpm php /tmp/at.php "03:30:00"' | grep -q "purge-unconfirmed.*DONE" || fail "7b: the second run of 03:30 did not run the purge"
    [ "$(q "select count(*) from leads")" = "$leads" ] || fail "7b: a second run of 03:30 deleted something"
    # schedule:run with the clock set also wrote a heartbeat with that old time; the real scheduler replaces it.
    await "the scheduler's own heartbeat to replace it" 90 'health_ok'
    ok "b. schedule:run at 03:29 left the two stale subscriptions, at 03:30 it deleted them, and the same minute run again deleted nothing more"

    echo "   c. Stopped for five minutes:"
    on 'docker compose stop scheduler' >/dev/null 2>&1
    t="$(now)"
    await "the scheduler check to fail" 400 'check_failing scheduler'
    echo "     $(($(now) - t)) s after stopping it the scheduler check fails: $(health_row scheduler | cut -c1-140)"
    [ "$(($(now) - t))" -ge 120 ] || fail "7c: the check failed after $(($(now) - t)) s, before the heartbeat could be old"
    sleep $((300 - ($(now) - t)))
    echo "     after 5 minutes:"
    echo "       $(health_row scheduler | cut -c1-160)"
    echo "       $(health_row queue | cut -c1-200)"
    echo "       /health: $(health_status); containers: scheduler $(state scheduler), queue-worker $(state queue-worker) (restarts $(restarts queue-worker))"
    check_failing scheduler && check_failing queue || fail "7c: the scheduler and the queue check do not both fail"
    [ "$(health_status)" = "503" ] || fail "7c: /health does not notice the missing scheduler"
    on 'docker compose start scheduler' >/dev/null 2>&1
    t2="$(now)"
    await "the stack healthy again" 300 'health_ok'
    await "every container healthy" 300 'all_containers_healthy'
    ok "c. the scheduler stopped for 5 minutes: /health reported it, and the queue as well, because the scheduler queues the queue's heartbeat; every check and container was healthy $(($(now) - t2)) s after the start, by itself"
}

# 8 -------------------------------------------------------------------------------------------------------------------
step_8() {
    step "8. The queue worker stopped for four minutes"
    await "the stack healthy" 180 'health_ok'
    on 'docker compose stop queue-worker' >/dev/null 2>&1
    t="$(now)"
    mail_reset
    work mails 3 s8- >/dev/null
    await "the queue check to fail" 400 'check_failing queue'
    echo "     $(($(now) - t)) s after stopping it: queue '$(health_row queue | cut -c1-200)'; scheduler: '$(health_row scheduler | cut -c1-80)'"
    echo "     /health: $(health_status); jobs waiting: $(queue_jobs) (3 mails, and one heartbeat a minute); mails delivered: $(mail_total)"
    [ "$(health_status)" = "503" ] || fail "8: /health does not notice the missing worker"
    sleep 60
    on 'docker compose start queue-worker' >/dev/null 2>&1
    await "the queue idle" 90 'queue_idle'
    await "3 mails" 60 'test "$(mail_total)" = "3"'
    await "the stack healthy again" 300 'health_ok'
    ok "8. the mails waited in Redis while the worker was stopped, were sent once it started, and /health went green again on its own"
}

# 9 -------------------------------------------------------------------------------------------------------------------
step_9() {
    step "9. The state at the end"
    await "every container healthy" 300 'all_containers_healthy'
    on 'docker compose exec -T php-fpm php artisan nusszopf:health' | sed 's/^/     /'
    on 'docker compose ps --format "table {{.Service}}\t{{.Status}}"' | sed 's/^/     /'
    [ "$(failed_jobs)" = "0" ] || fail "9: jobs are in failed_jobs"
    same_index_as_reference final
    [ "$(index_documents)" = "$(expected_documents)" ] || fail "9: the index does not hold what PostgreSQL says"
    ok "every container healthy, every check ok, failed_jobs empty, the index identical to the reference"
}

if [ "${QS_REUSE:-0}" = "1" ]; then
    docker exec "$HOST" true 2>/dev/null || { echo "QS_REUSE=1 but $HOST is not running" >&2; exit 2; }
    on "docker compose exec -T php-fpm sh -c 'cat > /tmp/work.php'" < "$ROOT/tests/QueueScheduler/work.php"
    [ -d "$WORK/reference" ] || snapshot reference
else
    setup
fi
for n in ${QS_STEPS:-1 2 3 4 5 6 7 8 9}; do "step_$n"; done
