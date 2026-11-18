#!/bin/sh
# RIZQIM M3: proof of PITR end to end (CI job `pitr-drill`; the same steps locally with --local).
#
#   scripts/pitr-ci.sh            CI / any host with Docker: builds the postgres and backup images, starts them with an
#                                 S3 target (infra/ci/docker-compose.pitr.yml, MinIO over TLS), migrates, then runs the
#                                 checks below through the production scripts (backup.sh, restore-drill.sh, pitr-drill.sh)
#   scripts/pitr-ci.sh --local    no Docker: run as root; two initdb clusters (live on PITR_CI_LIVE_PORT [5433], restored
#                                 copy on PITR_CI_RESTORE_PORT [5434]) under PITR_CI_WORK (required), a posix repository,
#                                 Postgres binaries from PITR_PG_BINDIR [/usr/lib/postgresql/16/bin]; the archive target
#                                 "goes down" by making the repository's archive directory unwritable
#   scripts/pitr-ci.sh --down     CI: stop and remove the compose stack and its volumes
#
# Checks: stanza-create + check + first full backup by the daemon's own init; batch A; full backup; batch B with a
# drill_probe token; T; batch C; restore-drill.sh --target-time T: A and B present, C absent, the token present, the
# drill's own checks pass, restore_drills row written. Prints RESTORE_SECONDS, RECOVERY_POINT, DATA_LOSS_SECONDS (also to
# $GITHUB_STEP_SUMMARY). Second case: the archive target goes down, a write is NOT archived and pg_stat_archiver shows
# the failure (what the wal_archive alert reads); the target comes back and archiving catches up by itself.
# Migrations: PITR_CI_MIGRATE=1 [default] runs `php artisan migrate` from the runner against the live database;
# 0 creates only the tables the scripts write (monitoring_markers, restore_drills) for a run without PHP.

set -eu

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
log() { printf '%s pitr-ci: %s\n' "$(date -u +%H:%M:%S)" "$*" >&2; }
die() { log "FAIL: $*"; exit 1; }

MODE=compose
case "${1:-}" in
    --local) MODE=local ;;
    --down)
        cd "$ROOT_DIR"
        docker compose -f docker-compose.yml -f infra/ci/docker-compose.pitr.yml down -v --remove-orphans
        exit 0
        ;;
    '' | --ci) ;;
    *) die "unknown argument: $1" ;;
esac

DB_NAME=rizqim_pitr
DB_USER=rizqim
ROWS=1000
results=

# --- mode: local ------------------------------------------------------------------------------------------------------
if [ "$MODE" = "local" ]; then
    [ "$(id -u)" = "0" ] || die "--local runs as root (like the backup container); cluster processes run as postgres"
    W=${PITR_CI_WORK:?set PITR_CI_WORK to an empty scratch directory}
    LIVE_PORT=${PITR_CI_LIVE_PORT:-5433}
    PITR_PG_BINDIR=${PITR_PG_BINDIR:-/usr/lib/postgresql/16/bin}
    export PITR_PG_BINDIR
    PATH="$PITR_PG_BINDIR:$PATH"; export PATH
    as_pg() { runuser -u postgres -- "$@"; }

    # Everything the production scripts read from the compose environment, pointed at the scratch tree
    export PITR_ENABLED=1 PITR_ENV_FILE="$ROOT_DIR/infra/postgres/pitr-env.sh"
    export PITR_PGDATA="$W/live" PITR_SOCKET_DIR="$W/s" PITR_PG_PORT="$LIVE_PORT" PITR_PG_USER="$DB_USER" PITR_PG_DATABASE="$DB_NAME"
    export PITR_REPO1_PATH="$W/repo" PITR_SPOOL_PATH="$W/spool" PITR_LOCK_PATH="$W/lock" PITR_LOG_PATH="$W/log"
    export PITR_SCRATCH_LOCK_PATH="$W/lock-scratch" PITR_SPOOL_MEASURE_PATH="$W/spool"
    export PITR_RESTORE_ROOT="$W/restore" PITR_RESTORE_PORT="${PITR_CI_RESTORE_PORT:-5434}" PITR_DRILL_SOCKET_DIR="$W/d"
    export PITR_RESTORE_SOCKET_DIR="$W/r"
    export BACKUP_DIR="$W/backups" PITR_STATE_DIR="$W/backups/.pitr" BACKUP_RETENTION_DAYS=1
    export PGHOST="$W/s" PGPORT="$LIVE_PORT" PGUSER="$DB_USER" PGDATABASE="$DB_NAME" PGPASSWORD=
    export PITR_INIT_TRIES=3
    # Small spool cap so case R2-1 can overflow it with a few forced segment switches
    export PITR_ARCHIVE_QUEUE_MAX="${PITR_CI_QUEUE_MAX:-48MB}"
    # a second (posix) repository standing in for S3, and a short grace for the gap check
    export PITR_TEST_REPO2_PATH="$W/repo2" PITR_GAP_GRACE=3
    ARCHIVE_TIMEOUT=${PITR_CI_ARCHIVE_TIMEOUT:-10}

    if [ -f "$W/live/postmaster.pid" ]; then as_pg pg_ctl -D "$W/live" -m immediate stop >/dev/null 2>&1 || true; fi
    for d in "$W"/restore/*/; do [ ! -f "$d/postmaster.pid" ] || as_pg pg_ctl -D "$d" -m immediate stop >/dev/null 2>&1 || true; done
    for d in live repo repo2 spool lock lock-push lock-scratch log restore backups s d r bin ep; do rm -rf "${W:?}/$d"; done
    mkdir -p "$W/live" "$W/repo" "$W/repo2" "$W/spool" "$W/lock" "$W/log" "$W/restore" "$W/backups" "$W/s" "$W/bin"
    chown postgres "$W" "$W/live" "$W/repo" "$W/repo2" "$W/spool" "$W/lock" "$W/log" "$W/restore" "$W/s"
    chmod 0700 "$W/live"
    # The archive_command must contain "pgbackrest" (pgBackRest checks it): same file name as in the image
    cp "$ROOT_DIR/infra/postgres/archive.sh" "$W/bin/pgbackrest-archive.sh"
    chmod 0755 "$W/bin" "$W/bin/pgbackrest-archive.sh"

    # The live cluster's flags, also used by pitr-switch.sh --local to start a switched-to copy
    export PITR_LOCAL_PG_OPTS="-p $LIVE_PORT -c listen_addresses=127.0.0.1 -c unix_socket_directories=$W/s -c wal_level=replica -c archive_mode=on -c archive_command='$W/bin/pgbackrest-archive.sh %p' -c archive_timeout=$ARCHIVE_TIMEOUT -c wal_compression=on -c max_wal_size=1GB"
    SWITCH="sh $SCRIPT_DIR/pitr-switch.sh --local"
    log "initdb live cluster ($W/live, port $LIVE_PORT)"
    as_pg initdb -D "$W/live" -U "$DB_USER" --auth=trust -E UTF8 --locale=C.UTF-8 >"$W/initdb.log" 2>&1 || { cat "$W/initdb.log" >&2; die "initdb"; }
    # Same flags as the compose `postgres` command (archive_timeout shortened for a quick local run)
    as_pg env PATH="$PATH" PITR_ENABLED=1 PITR_ENV_FILE="$PITR_ENV_FILE" PITR_PGDATA="$PITR_PGDATA" PITR_SOCKET_DIR="$PITR_SOCKET_DIR" \
        PITR_PG_PORT="$LIVE_PORT" PITR_PG_USER="$DB_USER" PITR_PG_DATABASE="$DB_NAME" PITR_REPO1_PATH="$PITR_REPO1_PATH" \
        PITR_SPOOL_PATH="$PITR_SPOOL_PATH" PITR_LOCK_PATH="$W/lock-push" PITR_LOG_PATH="$PITR_LOG_PATH" \
        pg_ctl -D "$W/live" -l "$W/live.log" -w -o "$PITR_LOCAL_PG_OPTS" start >/dev/null
    createdb "$DB_NAME"

    live_sql() { psql -X -A -t -q -v ON_ERROR_STOP=1 -c "$1"; }
    in_backup() { "$@"; }
    BACKUP_SH="sh $SCRIPT_DIR/backup.sh"
    DRILL="sh $SCRIPT_DIR/restore-drill.sh --local"
    PITR_RESTORE_SH="sh $SCRIPT_DIR/pitr-restore.sh --local"
    MIG_HOST=127.0.0.1; MIG_PORT=$LIVE_PORT; MIG_PASS=
    SMOKE_PORT=$PITR_RESTORE_PORT
    target_down() { find "$W/repo/archive" -type d -exec chmod 0500 {} +; }
    target_up() { find "$W/repo/archive" -type d -exec chmod 0750 {} +; }
    repo1_down() { target_down; }
    repo1_up() { target_up; }
    INFO_WAIT=0

# --- mode: compose (CI) -------------------------------------------------------------------------------------------
else
    command -v docker >/dev/null 2>&1 || die "docker not found"
    cd "$ROOT_DIR"
    DC="docker compose -f docker-compose.yml -f infra/ci/docker-compose.pitr.yml"
    [ -f backend/.env ] || cp backend/.env.example backend/.env
    certs=${PITR_CI_CERT_DIR:-${RUNNER_TEMP:-/tmp}/pitr-ci-certs}
    mkdir -p "$certs/CAs"
    if [ ! -f "$certs/public.crt" ]; then
        openssl req -x509 -newkey rsa:2048 -nodes -days 2 -subj "/CN=minio" -addext "subjectAltName=DNS:minio" \
            -keyout "$certs/private.key" -out "$certs/public.crt" >/dev/null 2>&1 || die "openssl"
    fi
    chmod 0755 "$certs" "$certs/CAs"
    chmod 0644 "$certs/private.key" "$certs/public.crt"
    # Root .env from the example plus the CI values; every credential is random for this run. Never over a real .env
    if [ -f .env ] && ! grep -q '^# generated by scripts/pitr-ci.sh' .env && [ "${CI:-}" != "true" ]; then
        die "a root .env exists: pitr-ci.sh replaces it (run it in CI or in a scratch checkout)"
    fi
    { echo '# generated by scripts/pitr-ci.sh'; cat .env.example; } >.env
    DB_PASS=$(openssl rand -hex 12)
    s3_key=ci$(openssl rand -hex 6)
    s3_secret=$(openssl rand -hex 20)
    # the gap grace time of the daemon (.env) and of this script's waits and calls must be the same
    export PITR_GAP_GRACE=20
    cat >>.env <<ENV
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS
PITR_ENABLED=1
PITR_CIPHER_PASS=$(openssl rand -hex 24)
PITR_ARCHIVE_QUEUE_MAX=48MB
PITR_GAP_GRACE=$PITR_GAP_GRACE
PITR_S3_ENDPOINT=https://minio:9000
PITR_S3_BUCKET=rizqim-pitr
PITR_S3_ACCESS_KEY=$s3_key
PITR_S3_SECRET=$s3_secret
PITR_S3_REGION=us-east-1
PITR_S3_VERIFY_TLS=n
PITR_CI_CERT_DIR=$certs
ENV
    PG_PORT=${PITR_CI_PG_PORT:-55432}
    log "building the postgres and backup images"
    $DC build postgres backup
    $DC run --rm --no-deps --entrypoint pgbackrest backup version
    log "starting MinIO and creating the bucket"
    $DC up -d minio
    i=0
    until $DC run --rm --no-deps --entrypoint rclone \
        -e RCLONE_CONFIG_CI_TYPE=s3 -e RCLONE_CONFIG_CI_PROVIDER=Minio -e RCLONE_CONFIG_CI_ENDPOINT=https://minio:9000 \
        -e RCLONE_CONFIG_CI_ACCESS_KEY_ID="$s3_key" -e RCLONE_CONFIG_CI_SECRET_ACCESS_KEY="$s3_secret" \
        backup --no-check-certificate mkdir ci:rizqim-pitr >/dev/null 2>&1; do
        i=$((i + 1)); [ "$i" -lt 30 ] || { $DC logs minio >&2; die "MinIO did not come up"; }
        sleep 2
    done
    log "starting postgres and backup"
    $DC up -d --wait postgres
    $DC up -d backup
    export PGHOST=127.0.0.1 PGPORT="$PG_PORT" PGUSER="$DB_USER" PGPASSWORD="$DB_PASS" PGDATABASE="$DB_NAME"
    live_sql() { psql -X -A -t -q -v ON_ERROR_STOP=1 -c "$1"; }
    # test variables reach the scripts in the container
    in_backup() {
        set -- backup "$@"
        for v in PITR_GAP_GRACE PITR_HEAL_INTERVAL PITR_TEST_BACKUP_FAIL PITR_TEST_EXPIRE_FAIL; do
            eval "tv=\${$v:-}"
            # shellcheck disable=SC2154
            [ -z "$tv" ] || set -- -e "$v=$tv" "$@"
        done
        $DC exec -T "$@"
    }
    BACKUP_SH="in_backup sh /usr/local/bin/backup.sh"
    DRILL="sh $SCRIPT_DIR/restore-drill.sh"
    PITR_RESTORE_SH="sh $SCRIPT_DIR/pitr-restore.sh"
    SWITCH="sh $SCRIPT_DIR/pitr-switch.sh"
    MIG_HOST=127.0.0.1; MIG_PORT=$PG_PORT; MIG_PASS=$DB_PASS
    SMOKE_PORT=${PITR_CI_RESTORE_PORT:-55499}
    target_down() { $DC stop minio; }
    target_up() { $DC start minio; }
    # repo1 (the local volume) unwritable for the postgres container's archive-push
    repo1_down() { $DC exec -T backup sh -c 'find /var/lib/pgbackrest/archive -type d -exec chmod 0500 {} +'; }
    repo1_up() { $DC exec -T backup sh -c 'find /var/lib/pgbackrest/archive -type d -exec chmod 0750 {} +'; }
    INFO_WAIT=1
    # restore-drill.sh and pitr-restore.sh call plain `docker compose`: point it at both files
    export COMPOSE_FILE="docker-compose.yml:infra/ci/docker-compose.pitr.yml"
fi

summary() { results="$results$1
"; log "$1"; }

# --- migrations ---------------------------------------------------------------------------------------------------
if [ "${PITR_CI_MIGRATE:-1}" = "1" ]; then
    log "php artisan migrate (live database)"
    cfg=$(mktemp -d "${TMPDIR:-/tmp}/pitr-ci-cache.XXXXXX")
    (cd "$ROOT_DIR/backend" && env APP_ENV=pitr-ci APP_DEBUG=false APP_KEY="base64:$(openssl rand -base64 32)" \
        DB_CONNECTION=pgsql DB_HOST="$MIG_HOST" DB_PORT="$MIG_PORT" DB_DATABASE="$DB_NAME" DB_USERNAME="$DB_USER" \
        DB_PASSWORD="$MIG_PASS" DB_URL= DB_POOLER=direct CACHE_STORE=array QUEUE_CONNECTION=sync SESSION_DRIVER=array \
        BROADCAST_CONNECTION=null LOG_CHANNEL=stderr APP_CONFIG_CACHE="$cfg/config.php" APP_ROUTES_CACHE="$cfg/routes.php" \
        APP_EVENTS_CACHE="$cfg/events.php" php artisan migrate --force --no-interaction) >"${TMPDIR:-/tmp}/pitr-ci-migrate.log" 2>&1 \
        || { tail -n 40 "${TMPDIR:-/tmp}/pitr-ci-migrate.log" >&2; die "migrate failed"; }
    rm -rf "$cfg"
else
    log "minimal schema (PITR_CI_MIGRATE=0)"
    live_sql "CREATE TABLE IF NOT EXISTS monitoring_markers (key varchar(64) PRIMARY KEY, recorded_at timestamp(0) NOT NULL, meta json)" >/dev/null
    live_sql "CREATE TABLE IF NOT EXISTS restore_drills (id bigserial PRIMARY KEY, started_at timestamp(0), finished_at timestamp(0), status varchar(16), repo varchar(8), target_time timestamp(0), reached_time timestamp(0), restore_seconds integer, data_loss_seconds integer, backup_label varchar(64), checks json, error text, created_at timestamp(0), updated_at timestamp(0))" >/dev/null
fi

# --- 1. the daemon's own init: stanza-create, check, first full backup --------------------------------------------------
if [ "$INFO_WAIT" = "1" ]; then
    # The daemon's init ends with a full backup, expire and the pitr marker: wait for that marker, so no step below
    # races the daemon for pgBackRest's backup lock
    log "waiting for the backup daemon: stanza + check + first full backup"
    i=0
    until [ "$(live_sql "SELECT meta->>'last_full_at' IS NOT NULL FROM monitoring_markers WHERE key = 'pitr'" 2>/dev/null)" = "t" ]; do
        i=$((i + 1)); [ "$i" -lt 120 ] || { $PITR_RESTORE_SH --info >&2 || true; $DC logs backup postgres >&2; die "no full backup from the daemon after 6 min"; }
        sleep 3
    done
else
    $BACKUP_SH --pitr-init || die "backup.sh --pitr-init"
fi
summary "stanza + check + first full backup: ok"
# The server must never restart under the archiver (a crash-restart once came from pgBackRest's async worker)
started=$(live_sql "SELECT pg_postmaster_start_time()")

batch() { live_sql "INSERT INTO pitr_ci_rows (batch, n) SELECT '$1', g FROM generate_series(1, $ROWS) g" >/dev/null; }
live_sql "CREATE TABLE IF NOT EXISTS pitr_ci_rows (id bigserial PRIMARY KEY, batch text NOT NULL, n int NOT NULL, created_at timestamptz NOT NULL DEFAULT clock_timestamp())" >/dev/null

# --- 2. A, full, B (+ probe token), T, C -------------------------------------------------------------------------------
batch A
$BACKUP_SH --pitr-backup full || die "backup.sh --pitr-backup full"
summary "full backup after batch A: ok"
token=$(cat /proc/sys/kernel/random/uuid)
batch B
live_sql "INSERT INTO monitoring_markers (key, recorded_at, meta) VALUES ('drill_probe', now() AT TIME ZONE 'UTC', json_build_object('token', '$token')) ON CONFLICT (key) DO UPDATE SET recorded_at = EXCLUDED.recorded_at, meta = EXCLUDED.meta" >/dev/null
T=$(live_sql "SELECT to_char(date_trunc('second', clock_timestamp()) + interval '1 second', 'YYYY-MM-DD HH24:MI:SS')")
until [ "$(live_sql "SELECT clock_timestamp() > '$T+00'::timestamptz")" = "t" ]; do sleep 0.3; done
batch C
log "T = $T UTC (A, B before; C after)"

# markers written by the production code path
$BACKUP_SH --markers || die "backup.sh --markers"
[ "$(live_sql "SELECT meta->>'enabled' = 'true' AND meta->>'last_full_at' IS NOT NULL AND meta->>'status' = 'ok' FROM monitoring_markers WHERE key = 'pitr'")" = "t" ] \
    || { live_sql "SELECT meta FROM monitoring_markers WHERE key = 'pitr'" >&2; die "pitr marker incomplete"; }
[ "$(live_sql "SELECT (meta->>'fs_total_bytes')::bigint > 0 AND (meta->>'pg_wal_bytes')::bigint > 0 FROM monitoring_markers WHERE key = 'disk'")" = "t" ] \
    || { live_sql "SELECT meta FROM monitoring_markers WHERE key = 'disk'" >&2; die "disk marker incomplete"; }
summary "monitoring_markers pitr + disk: ok"

# --- 3. the drill to T ------------------------------------------------------------------------------------------------
export PITR_DRILL_EXTRA_CHECK_SQL="SELECT (SELECT count(*) FROM pitr_ci_rows WHERE batch = 'A') || ':' || (SELECT count(*) FROM pitr_ci_rows WHERE batch = 'B') || ':' || (SELECT count(*) FROM pitr_ci_rows WHERE batch = 'C')"
export PITR_DRILL_EXTRA_CHECK_EXPECT="$ROWS:$ROWS:0"
# App smoke from this host against the restored copy (exit 77 when the app has no db:restore-smoke yet)
export PITR_SMOKE_DB_PORT="$SMOKE_PORT"
smoke_key=$(openssl rand -base64 32)
export PITR_SMOKE_CMD="cd '$ROOT_DIR/backend' && export APP_ENV=pitr-ci APP_KEY='base64:$smoke_key' DB_CONNECTION=pgsql DB_DATABASE='$DB_NAME' DB_USERNAME='$DB_USER' DB_PASSWORD='$MIG_PASS' DB_URL= CACHE_STORE=array QUEUE_CONNECTION=sync SESSION_DRIVER=array BROADCAST_CONNECTION=null LOG_CHANNEL=stderr && { php artisan help db:restore-smoke >/dev/null 2>&1 || exit 77; } && php artisan db:restore-smoke"
out=$(mktemp "${TMPDIR:-/tmp}/pitr-ci-drill.XXXXXX")
rc=0
$DRILL --target-time "$T+00" --expect-token "$token" --ci >"$out" || rc=$?
cat "$out"
val() { sed -n "s/^$1=//p" "$out" | tail -n 1; }
if [ "$rc" != "0" ] || [ "$(val DRILL_STATUS)" != "ok" ]; then die "drill failed (exit $rc)"; fi
[ "$(val CHECK_extra)" = "ok" ] || die "A+B present / C absent check failed"
[ "$(val CHECK_probe)" = "ok" ] || die "probe token check failed"
if [ "$(live_sql "SELECT to_regclass('public.restore_drills') IS NOT NULL")" = "t" ]; then
    [ "$(live_sql "SELECT count(*) FROM restore_drills WHERE status = 'ok'")" -ge 1 ] || die "no restore_drills row"
    summary "restore_drills row: ok"
else
    summary "restore_drills row: skipped (table not present in this migration set)"
fi
RESTORE_SECONDS=$(val RESTORE_SECONDS); RECOVERY_POINT=$(val RECOVERY_POINT); DATA_LOSS_SECONDS=$(val DATA_LOSS_SECONDS)
if (cd "$ROOT_DIR/backend" && php artisan help db:restore-smoke >/dev/null 2>&1); then
    [ "$(val CHECK_app_smoke)" = "ok" ] || die "the app smoke (db:restore-smoke) did not pass on the restored copy"
fi
[ "$(val TRIGGER)" = "manual" ] || die "a restore-drill.sh run must record trigger=manual"
summary "restore to T: A+B present, C absent, probe token present, smoke: $(val CHECK_app_smoke)"
summary "RESTORE_SECONDS=$RESTORE_SECONDS"
summary "RECOVERY_POINT=$RECOVERY_POINT (T = $T)"
summary "DATA_LOSS_SECONDS=$DATA_LOSS_SECONDS"

# --- 4. archive target down, then back ------------------------------------------------------------------------------
before=$(live_sql "SELECT failed_count FROM pg_stat_archiver")
log "archive target DOWN"
target_down
batch D
seg=$(live_sql "SELECT pg_walfile_name(pg_current_wal_lsn())")
live_sql "SELECT pg_switch_wal()" >/dev/null
i=0
until [ "$(live_sql "SELECT failed_count > $before AND last_failed_time > coalesce(last_archived_time, '-infinity') FROM pg_stat_archiver")" = "t" ]; do
    i=$((i + 1)); [ "$i" -lt 180 ] || { target_up; die "archive failure not visible in pg_stat_archiver after 180 s"; }
    sleep 1
done
[ "$(live_sql "SELECT NOT (last_archived_wal ~ '^[0-9A-F]{24}\$' AND last_archived_wal >= '$seg') FROM pg_stat_archiver")" = "t" ] || die "segment $seg archived while the target was down"
summary "target down: segment $seg not archived, failure visible in pg_stat_archiver (failed_count $(live_sql "SELECT failed_count FROM pg_stat_archiver"))"
down_at=$(date -u +%s)
log "archive target UP"
target_up
i=0
until [ "$(live_sql "SELECT last_archived_wal ~ '^[0-9A-F]{24}\$' AND last_archived_wal >= '$seg' AND last_archived_time > last_failed_time FROM pg_stat_archiver")" = "t" ]; do
    i=$((i + 1)); [ "$i" -lt 300 ] || die "archiving did not catch up within 300 s after the target came back"
    sleep 1
done
summary "target back: archiving caught up in $(($(date -u +%s) - down_at)) s"
$BACKUP_SH --markers || die "backup.sh --markers"
[ "$(live_sql "SELECT pg_postmaster_start_time()")" = "$started" ] || die "the live server restarted during the run (crash-restart? see the postgres log)"
summary "live server never restarted: ok"

# --- R2-1: the spool cap overflows while repo1 is unwritable: pgBackRest drops WAL and reports success ---------------
log "R2-1: WAL gap: repo1 unwritable, the archive queue (48MB) overflows"
repo1_down
for i in 1 2 3 4 5 6 7 8; do
    live_sql "INSERT INTO pitr_ci_rows (batch, n) SELECT 'G', g FROM generate_series(1, 20000) g" >/dev/null
    live_sql "SELECT pg_switch_wal()" >/dev/null
    sleep 1
done
i=0
# The cap overflows: pgBackRest drops the queue and answers success, so the .ready files go and the archiver "recovers"
until [ "$(live_sql "SELECT (SELECT count(*) FROM pg_ls_archive_statusdir() WHERE name LIKE '%.ready') = 0 AND failed_count > 0 AND last_archived_time > coalesce(last_failed_time, '-infinity') FROM pg_stat_archiver")" = "t" ]; do
    i=$((i + 1)); [ "$i" -lt 240 ] || { repo1_up; die "the archiver neither dropped nor failed"; }
    sleep 1
done
repo1_up
live_sql "INSERT INTO pitr_ci_rows (batch, n) VALUES ('H', 1)" >/dev/null
seg=$(live_sql "SELECT pg_walfile_name(pg_current_wal_lsn())")
live_sql "SELECT pg_switch_wal()" >/dev/null
i=0
until [ "$(live_sql "SELECT last_archived_wal >= '$seg' FROM pg_stat_archiver")" = "t" ]; do
    i=$((i + 1)); [ "$i" -lt 240 ] || die "archiving did not resume after repo1 came back"
    sleep 1
done
sleep $((${PITR_GAP_GRACE:-3} + 2))
$BACKUP_SH --markers || die "backup.sh --markers"
gap_status=$(live_sql "SELECT (meta->>'status') || '|' || coalesce(meta->'gap'->>'detected', 'none') || '|' || coalesce(meta->'gap'->>'backup_after', '') FROM monitoring_markers WHERE key = 'pitr'")
# error while nothing after the gap is restorable; in CI the daemon may already have taken the full backup (ok|true|<label>)
case "$gap_status" in "error|true|" | "ok|true|"?*) ;; *) live_sql "SELECT meta FROM monitoring_markers WHERE key = 'pitr'" >&2; die "R2-1: the WAL gap was not detected (marker status|gap|backup_after = $gap_status)" ;; esac
summary "R2-1: WAL gap detected: marker status=error ($(live_sql "SELECT meta->>'message' FROM monitoring_markers WHERE key = 'pitr'"))"
$PITR_RESTORE_SH --info | grep -q "uzilgan" || die "R2-1: pitr-restore.sh --info does not report the gap"
# A full backup must follow at once (the daemon in CI; locally the same function the daemon's loop calls)
if [ "$INFO_WAIT" = "1" ]; then
    i=0
    until [ "$(live_sql "SELECT meta->>'status' = 'ok' AND meta->'gap'->>'backup_after' IS NOT NULL FROM monitoring_markers WHERE key = 'pitr'")" = "t" ]; do
        i=$((i + 1)); [ "$i" -lt 100 ] || die "R2-1: the daemon took no full backup after the gap"
        sleep 3
    done
else
    $BACKUP_SH --pitr-heal || die "R2-1: backup.sh --pitr-heal"
fi
[ "$(live_sql "SELECT meta->>'status' = 'ok' AND meta->'gap'->>'backup_after' IS NOT NULL FROM monitoring_markers WHERE key = 'pitr'")" = "t" ] || die "R2-1: still no restorable point after the gap"
unset PITR_DRILL_EXTRA_CHECK_SQL PITR_DRILL_EXTRA_CHECK_EXPECT
$DRILL --ci >"$out" || { cat "$out"; die "R2-1: drill after the gap and the new full backup failed"; }
summary "R2-1: new full backup after the gap, restorable window: $($PITR_RESTORE_SH --info | sed -n 's/^Tiklash mumkin (repo1, \(.*\))/\1/p' | tr '\n' ';'), drill ok"

# --- RR2-5 / RR2-4 / RR2-3 (local: the repositories are directories here) -------------------------------------------
if [ "$MODE" = "local" ]; then
    for i in 1 2 3; do batch "S$i"; live_sql "SELECT pg_switch_wal()" >/dev/null; done
    seg=$(live_sql "SELECT pg_walfile_name(pg_current_wal_lsn())"); live_sql "SELECT pg_switch_wal()" >/dev/null
    i=0; until [ "$(live_sql "SELECT last_archived_wal >= '$seg' FROM pg_stat_archiver")" = "t" ]; do i=$((i + 1)); [ "$i" -lt 240 ] || die "WAL not archived"; sleep 1; done
    sleep 2
    second_newest() { find "$1/archive" -type f -name '0000000[0-9A-F]*-*' | sort | tail -n 2 | head -n 1; }
    marker() { live_sql "SELECT meta->>'$1' FROM monitoring_markers WHERE key = 'pitr'"; }
    # a younger hole never hides or clears an older open gap (the real check_gaps, pgBackRest stubbed)
    log "gap check: two holes, the newer one inside the grace time"
    mkdir -p "$W/gapstub"
    cat >"$W/gapstub/case.sh" <<'CASE'
set -eu
. "$1/scripts/pitr.sh"
log() { :; }
PITR_STATE_DIR=$2; PITR_REPO2=off; PGBACKREST_STANZA=main; PITR_GAP_GRACE=600
eval "$(sed -n '/^need_set() {/,/^}/p;/^need_get() {/,/^}/p;/^need_clear() {/,/^}/p;/^check_gaps() {/,/^}/p;/^rv() {/p' "$1/scripts/backup.sh")"
pitr_val() { case "$1" in *timeline*) echo 1;; *wal_segment*) echo 16777216;; *) echo 0;; esac; }
NOW=$(date -u +%s)
seg() { printf '"16-1/0000000100000000/0000000100000000000000%s-abc.gz":{"type":"file","size":1,"time":%s},' "$1" "$2"; }
B='{"archive":{"start":"000000010000000000000001","stop":"000000010000000000000001"},"label":"F1","timestamp":{"start":1,"stop":'$((NOW - 9000))'}}'
LIST="$(seg 01 $((NOW - 9000)); seg 02 $((NOW - 8900)); seg 03 $((NOW - 8800)); seg 05 $((NOW - 7200)); seg 06 $((NOW - 7100)))"
pgbr() { case "$1" in repo-ls) printf '{%s"x":{"type":"path"}}' "$LIST" ;; info) printf '[{"backup":[%s]}]' "$B" ;; esac; }
check_gaps; r1="$GAP_OPEN:$(need_get 1):$GAP_AFTER"
LIST="$LIST$(seg 07 $((NOW - 200)); seg 09 $((NOW - 120)))"
check_gaps; r2="$GAP_OPEN:$(need_get 1):$GAP_AFTER"
B="$B"',{"archive":{"start":"000000010000000000000009","stop":"000000010000000000000009"},"label":"F2","timestamp":{"start":1,"stop":'$((NOW - 100))'}}'
check_gaps; r3="$GAP_OPEN:$(need_get 1):$GAP_BACKUP"
echo "$r1|$r2|$r3"
CASE
    rm -rf "$W/gapstub/state"; mkdir -p "$W/gapstub/state"
    got=$(sh "$W/gapstub/case.sh" "$ROOT_DIR" "$W/gapstub/state")
    [ "$got" = "1:gap:000000010000000000000003|1:gap:000000010000000000000003|0::F2" ] || die "gap check: a younger hole changed the verdict on an older open gap ($got)"
    summary "gap check: an old open gap stays open (and needed) while a younger hole is inside the grace time; a full after both closes it"
    # RR2-5: a hole younger than the grace time is not a gap; a need it caused is cleared when it closes
    log "RR2-5: a segment missing for one check (out-of-order arrival) is not a gap"
    f=$(second_newest "$W/repo"); mv "$f" "$f.away"
    PITR_GAP_GRACE=600 $BACKUP_SH --markers || die "backup.sh --markers"
    { [ "$(marker status)" = "ok" ] && [ ! -f "$W/backups/.pitr/need-full-repo1" ]; } || die "RR2-5: a hole within the grace time raised an error or a full backup"
    PITR_GAP_GRACE=0 $BACKUP_SH --markers || die "backup.sh --markers"
    { [ "$(marker status)" = "error" ] && [ -f "$W/backups/.pitr/need-full-repo1" ]; } || die "RR2-5: a hole older than the grace time was not reported"
    mv "$f.away" "$f"
    PITR_GAP_GRACE=0 $BACKUP_SH --markers || die "backup.sh --markers"
    { [ "$(marker status)" = "ok" ] && [ ! -f "$W/backups/.pitr/need-full-repo1" ]; } || die "RR2-5: the need for a full backup stayed after the hole closed"
    summary "RR2-5: a hole within the grace time is ignored; after it reported, the need is cleared once the segment arrives"
    # RR2-4: an old, healed gap in repo1 must not hide a new open gap in repo2
    log "RR2-4: healed gap in repo1 + open gap in repo2"
    f=$(second_newest "$W/repo2"); mv "$f" "$f.away"
    PITR_GAP_GRACE=0 $BACKUP_SH --markers || die "backup.sh --markers"
    [ "$(live_sql "SELECT meta->>'status' || '|' || (meta->'gap'->>'repo') FROM monitoring_markers WHERE key = 'pitr'")" = "error|2" ] \
        || { live_sql "SELECT meta FROM monitoring_markers WHERE key = 'pitr'" >&2; die "RR2-4: the open gap in repo2 is not the reported one"; }
    { [ -f "$W/backups/.pitr/need-full-repo2" ] && [ ! -f "$W/backups/.pitr/need-full-repo1" ]; } || die "RR2-4: the need is not set for repo2 alone"
    n1=$(sh "$SCRIPT_DIR/pitr-restore.sh" --local --info | grep -c 'repo1: backup set' || true)
    PITR_GAP_GRACE=0 PITR_HEAL_INTERVAL=0 $BACKUP_SH --pitr-heal || die "RR2-4: backup.sh --pitr-heal"
    [ "$(sh "$SCRIPT_DIR/pitr-restore.sh" --local --info | grep -c 'repo1: backup set' || true)" = "$n1" ] || die "RR2-4: the heal took a backup in repo1 too"
    [ ! -f "$W/backups/.pitr/need-full-repo2" ] || die "RR2-4: repo2 still needs a full backup after the heal"
    mv "$f.away" "$f"
    summary "RR2-4: repo2's open gap reported over repo1's healed one; the heal took a full in repo2 only"
    # RR2-3: one heal attempt per interval, per repository; heal fulls never expire the history from before
    log "RR2-3: repo2's backup keeps failing during a heal"
    printf outage >"$W/backups/.pitr/need-full-repo1"; printf outage >"$W/backups/.pitr/need-full-repo2"
    rm -f "$W/backups/.pitr/heal-last"
    total() { sh "$SCRIPT_DIR/pitr-restore.sh" --local --info | grep -c ': backup set' || true; }
    t0=$(total)
    PITR_TEST_BACKUP_FAIL=2 $BACKUP_SH --pitr-heal && die "RR2-3: a heal with a failing repo2 reported success"
    t1=$(total)
    [ "$t1" = "$((t0 + 1))" ] || die "RR2-3: expected one new backup (repo1), $t0 -> $t1 (an expire ran or repo2 was not failing)"
    { [ ! -f "$W/backups/.pitr/need-full-repo1" ] && [ -f "$W/backups/.pitr/need-full-repo2" ]; } || die "RR2-3: the need was not cleared per repository"
    # within the interval: no attempt at all (exit 0, nothing done)
    PITR_TEST_BACKUP_FAIL=2 $BACKUP_SH --pitr-heal || die "RR2-3: a heal within the interval failed instead of waiting"
    $BACKUP_SH --pitr-heal || die "RR2-3: a heal within the interval failed instead of waiting"
    [ "$(total)" = "$t1" ] || die "RR2-3: a second heal within the interval took another backup"
    [ "$(live_sql "SELECT meta->>'status' || '|' || (meta->'heal'->'needed'->>0) FROM monitoring_markers WHERE key = 'pitr'")" = "error|repo2" ] || die "RR2-3: the open heal is not in the marker"
    PITR_HEAL_INTERVAL=0 $BACKUP_SH --pitr-heal || die "RR2-3: the heal after the interval failed"
    { [ ! -f "$W/backups/.pitr/need-full-repo2" ] && [ ! -f "$W/backups/.pitr/heal-last" ]; } || die "RR2-3: the heal did not close the incident"
    [ "$(marker status)" = "ok" ] || die "RR2-3: marker not ok after the heal"
    summary "RR2-3: one heal attempt per interval, the need cleared per repository, no expire during the incident ($t0 -> $t1 backups)"
fi

# --- R2-5: a POSTGRES_DATA_VOLUME path without data never becomes a fresh, empty live database ------------------------
log "R2-5: POSTGRES_DATA_VOLUME pointing at a missing path"
if [ "$MODE" = "local" ]; then
    # the image's entrypoint guard, with a stand-in for the official entrypoint
    mkdir -p "$W/ep/bin" "$W/ep/empty"
    printf '#!/bin/sh\necho official-entrypoint-ran\n' >"$W/ep/bin/docker-entrypoint.sh"
    chmod 0755 "$W/ep/bin/docker-entrypoint.sh"
    rc=0; PATH="$W/ep/bin:$PATH" PGDATA="$W/ep/empty" RIZQIM_DATA_SOURCE=/srv/typo sh "$ROOT_DIR/infra/postgres/entrypoint.sh" postgres >"$W/ep/out" 2>&1 || rc=$?
    { [ "$rc" = "78" ] && grep -q "YARATILMAYDI" "$W/ep/out"; } || { cat "$W/ep/out" >&2; die "R2-5: the entrypoint guard did not refuse"; }
    [ "$(PATH="$W/ep/bin:$PATH" PGDATA="$W/live" RIZQIM_DATA_SOURCE='' sh "$ROOT_DIR/infra/postgres/entrypoint.sh" postgres)" = "official-entrypoint-ran" ] || die "R2-5: the guard blocks a normal start"
else
    missing="${RUNNER_TEMP:-/tmp}/rizqim-missing-pgdata-$$"
    POSTGRES_DATA_VOLUME=$missing $DC up -d --no-deps postgres
    i=0
    until $DC logs --no-color postgres 2>&1 | grep -q "YARATILMAYDI"; do
        i=$((i + 1)); [ "$i" -lt 60 ] || { $DC logs --no-color --tail=30 postgres >&2; die "R2-5: postgres did not refuse a missing data path"; }
        sleep 1
    done
    [ ! -f "$missing/PG_VERSION" ] || die "R2-5: an empty database was created in $missing"
    $DC up -d --no-deps --wait postgres
    [ "$(live_sql "SELECT count(*) FROM pitr_ci_rows WHERE batch = 'A'")" = "$ROWS" ] || die "R2-5: the real database did not come back"
fi
summary "R2-5: a POSTGRES_DATA_VOLUME path without data is refused (no empty database created)"

# --- helpers for the switch cases ----------------------------------------------------------------------------------
counts() { live_sql "SELECT string_agg(batch || '=' || n, ' ' ORDER BY batch) FROM (SELECT batch, count(*) n FROM pitr_ci_rows GROUP BY batch) x"; }
# Local mode: the scripts find the live data path in PITR_PGDATA (compose always mounts it at the same path)
sync_live() {
    [ "$MODE" = "local" ] || return 0
    if [ -f "$W/backups/.pitr/live-data" ]; then PITR_PGDATA=$(cat "$W/backups/.pitr/live-data"); else PITR_PGDATA=$W/live; fi
    export PITR_PGDATA
}
copy_sh() { # copy_sh <shell command run where the restored copies live (/restore in the backup container)>
    if [ "$MODE" = "local" ]; then (cd "$W/restore" && sh -c "$1"); else $DC exec -T -w /restore backup sh -c "$1"; fi
}
live_started() { live_sql "SELECT pg_postmaster_start_time()"; }
state_file() { if [ "$MODE" = "local" ]; then printf '%s' "$W/backups/.pitr/switch.state"; else printf '%s' .pitr-switch/switch.state; fi; }
# the live database is still the one it was ($1 = expected counts): up, writable, .env and state clean
assert_unchanged() { # assert_unchanged <expected counts> <what>
    sync_live
    [ "$(counts)" = "$1" ] || die "$2: the live database is not the one before: $(counts)"
    [ "$(live_sql "SELECT pg_is_in_recovery()")" = "f" ] || die "$2: the live database is not writable"
    [ ! -f "$(state_file)" ] || die "$2: switch state left behind"
    if [ "$MODE" = "compose" ] && [ "$(sed -n 's/^POSTGRES_DATA_VOLUME=//p' .env | tail -n 1)" != "${LIVE_SRC:-}" ]; then die "$2: .env does not point at the live database"; fi
}
# a point in time a retained backup covers, with a later commit archived; PIT_COUNTS = the data at PIT
point_in_time() { # point_in_time <batch written after the point>
    live_sql "SELECT pg_switch_wal()" >/dev/null
    sleep 2
    PIT=$(live_sql "SELECT to_char(clock_timestamp() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')")
    PIT_COUNTS=$(counts)
    sleep 1.2
    batch "$1"
    pseg=$(live_sql "SELECT pg_walfile_name(pg_current_wal_lsn())")
    live_sql "SELECT pg_switch_wal()" >/dev/null
    i=0
    until [ "$(live_sql "SELECT last_archived_wal >= '$pseg' FROM pg_stat_archiver")" = "t" ]; do i=$((i + 1)); [ "$i" -lt 240 ] || die "WAL not archived"; sleep 1; done
}
# a promoted, cleanly stopped copy at the current point in time
fresh_copy() { # fresh_copy <name> <batch written after the point>
    point_in_time "$2"
    $PITR_RESTORE_SH "$PIT+00" --into "$1" || die "pitr-restore.sh --into $1"
    $PITR_RESTORE_SH --stop "$1" || die "pitr-restore.sh --stop $1"
}
LIVE_SRC=
export PITR_SWITCH_APP_CMD="cd '$ROOT_DIR/backend' && export APP_ENV=pitr-ci APP_KEY='base64:$smoke_key' DB_CONNECTION=pgsql DB_HOST='$MIG_HOST' DB_PORT='$MIG_PORT' DB_DATABASE='$DB_NAME' DB_USERNAME='$DB_USER' DB_PASSWORD='$MIG_PASS' DB_URL= DB_POOLER=direct CACHE_STORE=array QUEUE_CONNECTION=sync SESSION_DRIVER=array BROADCAST_CONNECTION=null LOG_CHANNEL=stderr && ! php artisan migrate:status | grep -q Pending && { php artisan help db:restore-smoke >/dev/null 2>&1 || exit 0; } && php artisan db:restore-smoke"
before=$(counts)

# --- R2-3: a copy that is not fully recovered, or that cannot start, never becomes live -----------------------------
log "R2-3: a pending (not yet recovered) copy is refused; a copy that fails to start is rolled back automatically"
point_in_time P
$PITR_RESTORE_SH "$PIT+00" --into pend --no-start || die "pitr-restore.sh --into pend --no-start"
before=$(counts)
st0=$(live_started)
if $SWITCH pend --confirm "$DB_NAME"; then die "R2-3: a pending copy was switched to"; fi
if [ "$(live_started)" != "$st0" ] || [ "$(counts)" != "$before" ]; then die "R2-3: the refused switch touched the live database"; fi
$PITR_RESTORE_SH --drop pend || die "pitr-restore.sh --drop pend"
fresh_copy bad Q
before=$(counts)
copy_sh "echo \"shared_buffers = 'not-a-size'\" >> bad/postgresql.auto.conf"
if PITR_SWITCH_DB_TIMEOUT=60 $SWITCH bad --confirm "$DB_NAME"; then die "R2-3: a copy that cannot start was switched to"; fi
assert_unchanged "$before" "R2-3"
$PITR_RESTORE_SH --drop bad || die "pitr-restore.sh --drop bad"
summary "R2-3: pending copy refused (live untouched); a copy that cannot start was rolled back automatically"

# --- RR2-2: a signal (Ctrl-C, SSH drop, kill) at any phase of a switch puts the original back ----------------------
log "RR2-2: SIGTERM at every phase of a switch"
fresh_copy sig R
before=$(counts)
for ph in stopping switched started checked; do
    PITR_SWITCH_TEST_PAUSE_AT=$ph $SWITCH sig --confirm "$DB_NAME" >"${TMPDIR:-/tmp}/pitr-ci-sig.txt" 2>&1 &
    spid=$!
    i=0
    until [ -f "$(state_file).paused-$ph" ]; do
        i=$((i + 1)); [ "$i" -lt 600 ] || { kill "$spid" 2>/dev/null; cat "${TMPDIR:-/tmp}/pitr-ci-sig.txt" >&2; die "RR2-2: the switch never reached phase $ph"; }
        if ! kill -0 "$spid" 2>/dev/null; then cat "${TMPDIR:-/tmp}/pitr-ci-sig.txt" >&2; die "RR2-2: the switch ended before phase $ph"; fi
        sleep 0.5
    done
    kill -TERM "$spid"
    rc=0; wait "$spid" || rc=$?
    rm -f "$(state_file).paused-$ph"
    [ "$rc" != "0" ] || die "RR2-2: a switch killed at $ph reported success"
    grep -q "QAYTARILDI" "${TMPDIR:-/tmp}/pitr-ci-sig.txt" || { cat "${TMPDIR:-/tmp}/pitr-ci-sig.txt" >&2; die "RR2-2: no rollback after SIGTERM at $ph"; }
    assert_unchanged "$before" "RR2-2 ($ph)"
done
summary "RR2-2: SIGTERM at stopping/switched/started/checked: original database back each time"

# --- the forward-only model: a failed attempt after the copy ran live, then two successive switches ------------------
log "switch: an attempt that fails after postgres ran on the copy (stray WAL), then copy1 at P1, later copy0 at P0 < P1"
before=$(counts)
if PITR_SWITCH_TEST_FAIL_AT=checked $SWITCH sig --confirm "$DB_NAME"; then die "a switch failing at 'checked' succeeded"; fi
assert_unchanged "$before" "failed attempt"
$PITR_RESTORE_SH --drop sig || die "pitr-restore.sh --drop sig"
batch X0
point_in_time X1
p0=$PIT; c0=$PIT_COUNTS
point_in_time X2
p1=$PIT; c1=$PIT_COUNTS
$PITR_RESTORE_SH "$p1+00" --into copy1 || die "pitr-restore.sh --into copy1"
$PITR_RESTORE_SH --stop copy1 || die "pitr-restore.sh --stop copy1"
# a copy promoted at the same time as copy1 gets the same timeline: once copy1 is live it must be refused
$PITR_RESTORE_SH "$p0+00" --into stale0 || die "pitr-restore.sh --into stale0"
$PITR_RESTORE_SH --stop stale0 || die "pitr-restore.sh --stop stale0"
$SWITCH copy1 --confirm "$DB_NAME" || die "pitr-switch.sh copy1"
[ "$MODE" = "local" ] || LIVE_SRC=$(sed -n 's/^POSTGRES_DATA_VOLUME=//p' .env | tail -n 1)
sync_live
tla=$(live_sql "SELECT timeline_id FROM pg_control_checkpoint()")
[ "$(counts)" = "$c1" ] || die "copy1 should hold $c1: $(counts)"
[ ! -f "$(state_file)" ] || die "a completed switch left its state file"
$DRILL --ci >"$out" || { cat "$out"; die "drill after the switch to copy1 failed"; }
summary "switch 1: copy1 at P1 live (timeline $tla; the failed attempt's timeline was skipped), drill ok"
batch Y
if $SWITCH stale0 --confirm "$DB_NAME" 2>"${TMPDIR:-/tmp}/pitr-ci-stale.txt"; then die "a copy on an already used timeline was switched to"; fi
grep -q "eskirgan" "${TMPDIR:-/tmp}/pitr-ci-stale.txt" || { cat "${TMPDIR:-/tmp}/pitr-ci-stale.txt" >&2; die "the stale copy was refused for another reason"; }
$PITR_RESTORE_SH --drop stale0 || die "pitr-restore.sh --drop stale0"
summary "a copy promoted alongside copy1 (same timeline) is refused once copy1 is live"
# going back in time the standard way: a new copy at P0 < P1 (the original timeline), switched to like any copy
$PITR_RESTORE_SH "$p0+00" --into copy0 || die "pitr-restore.sh --into copy0"
$PITR_RESTORE_SH --stop copy0 || die "pitr-restore.sh --stop copy0"
$SWITCH copy0 --confirm "$DB_NAME" || die "pitr-switch.sh copy0"
[ "$MODE" = "local" ] || LIVE_SRC=$(sed -n 's/^POSTGRES_DATA_VOLUME=//p' .env | tail -n 1)
sync_live
tlb=$(live_sql "SELECT timeline_id FROM pg_control_checkpoint()")
[ "$tlb" -gt "$tla" ] || die "the second switch reused timeline $tlb (first $tla)"
[ "$(counts)" = "$c0" ] || die "copy0 should hold $c0: $(counts)"
$DRILL --ci >"$out" || { cat "$out"; die "drill after the switch to copy0 failed"; }
$BACKUP_SH --markers || die "backup.sh --markers"
[ "$(live_sql "SELECT meta->>'status' = 'ok' AND (meta->'gap'->>'detected') IS NULL AND meta->>'heal' IS NULL FROM monitoring_markers WHERE key = 'pitr'")" = "t" ] \
    || { live_sql "SELECT meta FROM monitoring_markers WHERE key = 'pitr'" >&2; die "after two switches the pitr marker is not clean"; }
summary "switch 2: copy0 at P0 < P1 live (timeline $tlb > $tla), drill ok, gap check clean"
# --recover after a switch that had completed (kill -9 between "complete" and removing the state): nothing is rolled back
st_before=$(live_started)
cur=$(counts)
{ echo "AT=2026-01-01 00:00:00"; echo "PREVIOUS=/nonexistent-previous"; echo "TARGET=/nonexistent-target"; echo "PHASE=stopping"; echo "PHASE=complete"; } >"$(state_file)"
$SWITCH --recover || die "--recover after a completed switch failed"
[ ! -f "$(state_file)" ] || die "--recover left the stale state of a completed switch"
{ [ "$(live_started)" = "$st_before" ] && [ "$(counts)" = "$cur" ]; } || die "--recover rolled back a completed switch"
summary "--recover after a completed switch: state removed, nothing rolled back"
# the previous live database: kept, listed, never started again, dropped only on purpose
$PITR_RESTORE_SH --list | grep -q "^copy1: avvalgi baza" || { $PITR_RESTORE_SH --list >&2; die "copy1 (the previous live database) is not listed as such"; }
if $PITR_RESTORE_SH --start copy1 2>/dev/null; then die "the previous live database was started"; fi
if $SWITCH copy1 --confirm "$DB_NAME" 2>/dev/null; then die "a switch to the previous live database was accepted"; fi
if $PITR_RESTORE_SH --drop copy1 </dev/null 2>/dev/null; then die "the previous live database was dropped without its name"; fi
$PITR_RESTORE_SH --drop copy1 --confirm copy1 || die "pitr-restore.sh --drop copy1 --confirm copy1"
summary "previous live database: listed, cannot be started or switched to, dropped only with its name"
# expire works normally afterwards: enough backups to rotate the retention (2 fulls), the newest stay restorable
batch Z1
$BACKUP_SH --pitr-backup full || die "full backup 1 after the switches"
batch Z2
$BACKUP_SH --pitr-backup diff || die "diff backup after the switches"
batch Z3
$BACKUP_SH --pitr-backup full || die "full backup 2 after the switches"
$BACKUP_SH --pitr-verify >"${TMPDIR:-/tmp}/pitr-ci-verify.txt" 2>&1 || { cat "${TMPDIR:-/tmp}/pitr-ci-verify.txt" >&2; die "pgbackrest verify does not accept the archive after the switches"; }
# full backups kept in repo1 (retention-full 2): the info lists each backup with the repository it is in
fulls=$($PITR_RESTORE_SH --info | awk '/^        full backup:/ { f = 1; next } /^        (diff|incr) backup:/ { f = 0 } f && /^            repo1: backup set/ { n++; f = 0 } END { print n + 0 }')
[ "$fulls" -le 2 ] || die "retention did not rotate: $fulls full backups in repo1"
$DRILL --ci >"$out" || { cat "$out"; die "drill after the retention rotation failed"; }
summary "after the switches: expire rotated the fulls ($fulls in repo1), verify ok, drill ok; history: $(sed -n 's/^repo1 timeline tarixi: //p' "${TMPDIR:-/tmp}/pitr-ci-verify.txt")"
rm -f "$out"

# --- journal archive: a detached month keeps the parent's triggers (real ones, from the migrations) ----------------
log "archive: side-database restore without the triggers' functions, re-attach of a month that carries the parent's triggers"
if [ "$MODE" = "local" ]; then ARCH_P="sh $SCRIPT_DIR/archive-partitions.sh --local"; ARCH_R="sh $SCRIPT_DIR/archive-restore.sh --local"; else
    ARCH_P="sh $SCRIPT_DIR/archive-partitions.sh"; ARCH_R="sh $SCRIPT_DIR/archive-restore.sh"; fi
mk_month() { # mk_month <table> <YYYY_MM> <from> <to>: a detached month as db:partitions:archive leaves it
    live_sql "CREATE TABLE public.$1_$2 (LIKE public.$1 INCLUDING DEFAULTS INCLUDING CONSTRAINTS INCLUDING INDEXES)" >/dev/null
    live_sql "SELECT replace(pg_get_triggerdef(oid), ' ON public.$1 ', ' ON public.$1_$2 ') FROM pg_trigger WHERE tgrelid = 'public.$1'::regclass AND NOT tgisinternal" \
        | while read -r def; do live_sql "$def" >/dev/null; done
    live_sql "INSERT INTO partition_archives (table_name, partition_name, range_from, range_to, status, detached_at, created_at, updated_at) VALUES ('$1', '$1_$2', '$3', '$4', 'detached', now() AT TIME ZONE 'UTC', now(), now())" >/dev/null
}
trig() { live_sql "SELECT coalesce(string_agg(tgname, ',' ORDER BY tgname), '') FROM pg_trigger WHERE tgrelid = 'public.$1'::regclass AND NOT tgisinternal"; }
mk_month fiscal_receipts 2025_06 2025-06-01 2025-07-01
store=$(live_sql "INSERT INTO stores (uuid, name, slug) VALUES (gen_random_uuid(), 'pitr-ci', 'pitr-ci-' || floor(random() * 1e9)::bigint) RETURNING id")
live_sql "INSERT INTO public.fiscal_receipts_2025_06 (uuid, store_id, type, payment_form, driver, idempotency_key, total, lines, payments, created_at, updated_at) SELECT gen_random_uuid(), $store, 'sale', 'cash', 'sandbox', 'pitr-ci-' || g, 1, '[]', '[]', '2025-06-15', '2025-06-15' FROM generate_series(1, 3) g" >/dev/null
mk_month audit_logs 2025_06 2025-06-01 2025-07-01
live_sql "INSERT INTO public.audit_logs_2025_06 (action, created_at) SELECT 'pitr-ci', '2025-06-15' FROM generate_series(1, 30) g" >/dev/null
[ -n "$(trig fiscal_receipts_2025_06)" ] || die "archive: the test month has no triggers (migrations without fiscal_receipt_keys?)"
keys_before=$(live_sql "SELECT count(*) FROM fiscal_receipt_keys")
$ARCH_P || die "archive-partitions.sh"
# side database: no fiscal_receipt_keys_insert() there, the restore must not need it
$ARCH_R fiscal_receipts 2025-06 || die "archive: side-database restore of a month with triggers"
[ "$(PGDATABASE=rizqim_archive live_sql "SELECT count(*) FROM fiscal_receipts_2025_06")" = "3" ] || die "archive: side database should hold 3 receipts"
# re-attach while the month is still in the live database (detached, not dropped)
$ARCH_R fiscal_receipts 2025-06 --attach --yes || die "archive: --attach of a month carrying the parent's triggers"
[ "$(trig fiscal_receipts_2025_06)" = "$(trig fiscal_receipts)" ] || die "archive: re-attached fiscal month triggers $(trig fiscal_receipts_2025_06) != parent $(trig fiscal_receipts)"
[ "$(live_sql "SELECT count(*) FROM fiscal_receipt_keys")" = "$keys_before" ] || die "archive: ATTACH changed fiscal_receipt_keys"
[ "$(live_sql "SELECT count(*) FROM fiscal_receipts WHERE idempotency_key LIKE 'pitr-ci-%'")" = "3" ] || die "archive: re-attached receipts not visible through the parent"
# RR2-9: a month carrying a trigger the parent does not have: the check inside the transaction rolls the ATTACH back
mk_month audit_logs 2025_07 2025-07-01 2025-08-01
live_sql "CREATE TRIGGER pitr_ci_extra BEFORE UPDATE ON public.audit_logs_2025_07 FOR EACH ROW EXECUTE FUNCTION suppress_redundant_updates_trigger()" >/dev/null
if $ARCH_R audit_logs 2025-07 --attach --yes 2>/dev/null; then die "RR2-9: a month with a foreign trigger was attached"; fi
[ "$(live_sql "SELECT count(*) FROM pg_inherits WHERE inhrelid = 'public.audit_logs_2025_07'::regclass")" = "0" ] || die "RR2-9: the refused ATTACH stayed"
[ "$(live_sql "SELECT status FROM partition_archives WHERE partition_name = 'audit_logs_2025_07'")" = "detached" ] || die "RR2-9: the catalogue changed although nothing was attached"
summary "RR2-9: a trigger mismatch rolls the ATTACH back (catalogue unchanged)"
# re-attach after the month was dropped: restored from the archive file, then attached
live_sql "DROP TABLE public.audit_logs_2025_06" >/dev/null
$ARCH_R audit_logs 2025-06 --attach --yes || die "archive: --attach of a dropped audit month from its file"
[ "$(trig audit_logs_2025_06)" = "$(trig audit_logs)" ] || die "archive: re-attached audit month triggers differ from the parent's"
[ "$(live_sql "SELECT count(*) FROM audit_logs WHERE action = 'pitr-ci'")" = "30" ] || die "archive: re-attached audit rows not visible"
if live_sql "DELETE FROM audit_logs WHERE action = 'pitr-ci'" >/dev/null 2>&1; then die "archive: the re-attached audit month is not append-only"; fi
summary "archive: side restore without trigger functions ok; re-attach (live table and from file) has exactly the parent's triggers ($(trig fiscal_receipts)); fiscal_receipt_keys unchanged; audit stays append-only"

printf '%s' "$results" >&2
if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
    {
        echo "### PITR drill (pgBackRest, compose topology)"
        echo
        echo '```'
        printf '%s' "$results"
        echo '```'
    } >>"$GITHUB_STEP_SUMMARY"
fi
log "PASS"
