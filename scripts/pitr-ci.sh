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
    ARCHIVE_TIMEOUT=${PITR_CI_ARCHIVE_TIMEOUT:-10}

    if [ -f "$W/live/postmaster.pid" ]; then as_pg pg_ctl -D "$W/live" -m immediate stop >/dev/null 2>&1 || true; fi
    for d in "$W"/restore/*/; do [ ! -f "$d/postmaster.pid" ] || as_pg pg_ctl -D "$d" -m immediate stop >/dev/null 2>&1 || true; done
    for d in live repo spool lock lock-push lock-scratch log restore backups s d r bin; do rm -rf "${W:?}/$d"; done
    mkdir -p "$W/live" "$W/repo" "$W/spool" "$W/lock" "$W/log" "$W/restore" "$W/backups" "$W/s" "$W/bin"
    chown postgres "$W" "$W/live" "$W/repo" "$W/spool" "$W/lock" "$W/log" "$W/restore" "$W/s"
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
    cat >>.env <<ENV
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASS
PITR_ENABLED=1
PITR_CIPHER_PASS=$(openssl rand -hex 24)
PITR_ARCHIVE_QUEUE_MAX=48MB
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
    # shellcheck disable=SC2086
    in_backup() { $DC exec -T ${PITR_TEST_FORGET_FAIL:+-e PITR_TEST_FORGET_FAIL=$PITR_TEST_FORGET_FAIL} backup "$@"; }
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
$BACKUP_SH --markers || true
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
$BACKUP_SH --markers || true
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
$BACKUP_SH --markers || true
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

# --- 5. switch-over rehearsal: the restored copy at T becomes the live database, then back --------------------------
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
before=$(counts)

# --- R2-3: a copy that is not fully recovered, or that cannot start, never becomes live -----------------------------
log "R2-3: a pending (not yet recovered) copy is refused; a copy that fails to start is rolled back automatically"
export PITR_SWITCH_APP_CMD=true
$PITR_RESTORE_SH "$T+00" --into pend --no-start || die "pitr-restore.sh --into pend --no-start"
st0=$(live_started)
if $SWITCH pend --confirm "$DB_NAME"; then die "R2-3: a pending copy was switched to"; fi
if [ "$(live_started)" != "$st0" ] || [ "$(counts)" != "$before" ]; then die "R2-3: the refused switch touched the live database"; fi
$PITR_RESTORE_SH --drop pend || die "pitr-restore.sh --drop pend"
$PITR_RESTORE_SH "$T+00" --into bad || die "pitr-restore.sh --into bad"
$PITR_RESTORE_SH --stop bad || die "pitr-restore.sh --stop bad"
copy_sh "echo \"shared_buffers = 'not-a-size'\" >> bad/postgresql.auto.conf"
if PITR_SWITCH_DB_TIMEOUT=60 $SWITCH bad --confirm "$DB_NAME"; then die "R2-3: a copy that cannot start was switched to"; fi
sync_live
[ "$(counts)" = "$before" ] || die "R2-3: after the failed switch the live database is not the original one: $(counts)"
[ "$(live_sql "SELECT pg_is_in_recovery()")" = "f" ] || die "R2-3: the original database did not come back"
if [ "$MODE" = "local" ]; then [ ! -f "$W/backups/.pitr/switch.state" ] || die "R2-3: switch state left behind"; else
    if [ -f .pitr-switch/switch.state ] || grep -q '^POSTGRES_DATA_VOLUME=' .env; then die "R2-3: .env or the switch state still point at the bad copy"; fi
fi
$PITR_RESTORE_SH --drop bad || die "pitr-restore.sh --drop bad"
summary "R2-3: pending copy refused (live untouched); a copy that cannot start was rolled back automatically"
log "switch rehearsal: restore to T into 'switchtest', stop it, make it live, then back (live before: $before)"
$PITR_RESTORE_SH "$T+00" --into switchtest || die "pitr-restore.sh --into switchtest"
$PITR_RESTORE_SH --stop switchtest || die "pitr-restore.sh --stop switchtest"
# migrate:status (nothing pending) and db:restore-smoke against the live database once it is the copy
export PITR_SWITCH_APP_CMD="cd '$ROOT_DIR/backend' && export APP_ENV=pitr-ci APP_KEY='base64:$smoke_key' DB_CONNECTION=pgsql DB_HOST='$MIG_HOST' DB_PORT='$MIG_PORT' DB_DATABASE='$DB_NAME' DB_USERNAME='$DB_USER' DB_PASSWORD='$MIG_PASS' DB_URL= DB_POOLER=direct CACHE_STORE=array QUEUE_CONNECTION=sync SESSION_DRIVER=array BROADCAST_CONNECTION=null LOG_CHANNEL=stderr && ! php artisan migrate:status | grep -q Pending && { php artisan help db:restore-smoke >/dev/null 2>&1 || exit 0; } && php artisan db:restore-smoke"
$SWITCH switchtest --confirm "$DB_NAME" || die "pitr-switch.sh switchtest"
sync_live
after=$(counts)
[ "$after" = "A=$ROWS B=$ROWS" ] || die "after the switch the live database should hold A and B only, it holds: $after"
[ "$(live_sql "SELECT pg_is_in_recovery()")" = "f" ] || die "switched database still in recovery"
[ "$(live_sql "SELECT timeline_id FROM pg_control_checkpoint()")" -ge 2 ] || die "switched database is not on a new timeline"
summary "switch-over: the copy at T is live ($after, timeline $(live_sql "SELECT timeline_id FROM pg_control_checkpoint()")), full backup taken"
batch E
$PITR_RESTORE_SH --drop switchtest 2>/dev/null && die "pitr-restore.sh dropped the copy that is the live database"
# R2-6: a second switch while the first is not finished (back or finalize) is refused
# (T itself is gone by now: retention keeps the two newest full backups, both after T)
$PITR_RESTORE_SH "$(live_sql "SELECT to_char(clock_timestamp() AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS')")+00" --into second --no-start || die "pitr-restore.sh --into second"
if $SWITCH second --confirm "$DB_NAME" 2>"${TMPDIR:-/tmp}/pitr-ci-second.txt"; then die "R2-6: a second switch-over was accepted"; fi
grep -q "hali yakunlanmagan" "${TMPDIR:-/tmp}/pitr-ci-second.txt" || { cat "${TMPDIR:-/tmp}/pitr-ci-second.txt" >&2; die "R2-6: refused for another reason"; }
$PITR_RESTORE_SH --drop second || die "pitr-restore.sh --drop second"
summary "R2-6: a second switch-over before --back/--finalize is refused"
$SWITCH --back --confirm "$DB_NAME" || die "pitr-switch.sh --back"
sync_live
[ "$(counts)" = "$before" ] || die "after --back the live database should be as before ($before), it holds: $(counts)"
seg=$(live_sql "SELECT pg_walfile_name(pg_current_wal_lsn())")
live_sql "SELECT pg_switch_wal()" >/dev/null
i=0
until [ "$(live_sql "SELECT last_archived_wal ~ '^[0-9A-F]{24}\$' AND last_archived_wal >= '$seg' FROM pg_stat_archiver")" = "t" ]; do
    i=$((i + 1)); [ "$i" -lt 180 ] || die "archiving does not work after switching back"
    sleep 1
done
$PITR_RESTORE_SH --drop switchtest || die "pitr-restore.sh --drop switchtest"
summary "switch back: original data again ($before), archiving works"
# The PITR chain must survive the switch-back (a restore right after it uses the newest backup, on the old timeline)
unset PITR_DRILL_EXTRA_CHECK_SQL PITR_DRILL_EXTRA_CHECK_EXPECT
$DRILL --ci >"$out" || { cat "$out"; die "drill after the switch-back failed"; }
[ "$(val DRILL_STATUS)" = "ok" ] || { cat "$out"; die "drill after the switch-back failed"; }
summary "drill after the switch-back: ok (restore $(val RESTORE_SECONDS) s, smoke $(val CHECK_app_smoke))"

# --- R2-4 + R2-2: two successive switch-overs (finalize in between), then back with a failing cleanup -------------------
log "R2-4/R2-2: switch to copy1, finalize, switch to copy2, back with the timeline cleanup failing"
# a point in time a retained backup covers: after batch J, before batch J2
point_in_time() { # sets PIT (UTC) after the current batch and waits until a later batch is archived
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
batch J
point_in_time J2
exp1=$PIT_COUNTS
$PITR_RESTORE_SH "$PIT+00" --into copy1 || die "pitr-restore.sh --into copy1"
$PITR_RESTORE_SH --stop copy1 || die "pitr-restore.sh --stop copy1"
$SWITCH copy1 --confirm "$DB_NAME" || die "pitr-switch.sh copy1"
sync_live
tl1=$(live_sql "SELECT timeline_id FROM pg_control_checkpoint()")
$SWITCH --finalize --confirm "$DB_NAME" || die "pitr-switch.sh --finalize"
[ "$(counts)" = "$exp1" ] || die "R2-4: copy1 should hold $exp1: $(counts)"
batch K
point_in_time L
exp2=$PIT_COUNTS
$PITR_RESTORE_SH "$PIT+00" --into copy2 || die "pitr-restore.sh --into copy2 (a time on the timeline of copy1)"
$PITR_RESTORE_SH --stop copy2 || die "pitr-restore.sh --stop copy2"
$SWITCH copy2 --confirm "$DB_NAME" || die "pitr-switch.sh copy2"
sync_live
tl2=$(live_sql "SELECT timeline_id FROM pg_control_checkpoint()")
[ "$tl2" -gt "$tl1" ] || die "R2-4: the second promotion reused timeline $tl2 (first: $tl1): the repository did not know the first one"
[ "$(counts)" = "$exp2" ] || die "R2-4: copy2 should hold $exp2: $(counts)"
if $PITR_RESTORE_SH --drop copy1 2>/dev/null; then die "R2-6: copy1 (the way back) was dropped"; fi
summary "R2-4: second switch-over got timeline $tl2 (first $tl1); R2-6: the way back (copy1) cannot be dropped"
PITR_TEST_FORGET_FAIL=1 $SWITCH --back --confirm "$DB_NAME" || die "pitr-switch.sh --back (cleanup failing)"
sync_live
exp_back=$(printf '%s L=%s' "$exp2" "$ROWS")
[ "$(counts)" = "$exp_back" ] || die "R2-2: back on copy1 expected $exp_back: $(counts)"
[ "$(live_sql "SELECT meta->>'status' = 'error' AND meta->>'forget_pending' IS NOT NULL FROM monitoring_markers WHERE key = 'pitr'")" = "t" ] || {
    live_sql "SELECT meta FROM monitoring_markers WHERE key = 'pitr'" >&2; die "R2-2: the pending cleanup is not visible in the pitr marker"; }
# the dangerous path of the review: further backups while the cleanup is pending must not expire anything
PITR_TEST_FORGET_FAIL=1 $BACKUP_SH --pitr-backup diff || true
$BACKUP_SH --pitr-forget-timeline || die "R2-2: backup.sh --pitr-forget-timeline"
$BACKUP_SH --markers || true
[ "$(live_sql "SELECT meta->>'status' = 'ok' AND meta->>'forget_pending' IS NULL FROM monitoring_markers WHERE key = 'pitr'")" = "t" ] || die "R2-2: cleanup done but the marker is not ok"
$BACKUP_SH --pitr-verify >"${TMPDIR:-/tmp}/pitr-ci-verify.txt" 2>&1 || { cat "${TMPDIR:-/tmp}/pitr-ci-verify.txt" >&2; die "R2-4: pgbackrest verify does not accept the archive"; }
if ! grep -q "$(printf '%08X' "$tl1").history" "${TMPDIR:-/tmp}/pitr-ci-verify.txt" || ! grep -q "$(printf '%08X' "$tl2").history" "${TMPDIR:-/tmp}/pitr-ci-verify.txt"; then
    cat "${TMPDIR:-/tmp}/pitr-ci-verify.txt" >&2; die "R2-4: the timeline history files are not in the repository"
fi
$DRILL --ci >"$out" || { cat "$out"; die "R2-2/R2-4: drill after two switch-overs and a switch-back failed"; }
summary "R2-2: cleanup failure left expire suspended (marker error), then cleaned; R2-4: verify ok, history $(sed -n 's/^repo1 timeline tarixi: //p' "${TMPDIR:-/tmp}/pitr-ci-verify.txt"); drill ok"
rm -f "$out"

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
