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

    log "initdb live cluster ($W/live, port $LIVE_PORT)"
    as_pg initdb -D "$W/live" -U "$DB_USER" --auth=trust -E UTF8 --locale=C.UTF-8 >"$W/initdb.log" 2>&1 || { cat "$W/initdb.log" >&2; die "initdb"; }
    # Same flags as the compose `postgres` command (archive_timeout shortened for a quick local run)
    as_pg env PATH="$PATH" PITR_ENABLED=1 PITR_ENV_FILE="$PITR_ENV_FILE" PITR_PGDATA="$PITR_PGDATA" PITR_SOCKET_DIR="$PITR_SOCKET_DIR" \
        PITR_PG_PORT="$LIVE_PORT" PITR_PG_USER="$DB_USER" PITR_PG_DATABASE="$DB_NAME" PITR_REPO1_PATH="$PITR_REPO1_PATH" \
        PITR_SPOOL_PATH="$PITR_SPOOL_PATH" PITR_LOCK_PATH="$W/lock-push" PITR_LOG_PATH="$PITR_LOG_PATH" \
        pg_ctl -D "$W/live" -l "$W/live.log" -w -o "-p $LIVE_PORT -c listen_addresses=127.0.0.1 -c unix_socket_directories=$W/s -c wal_level=replica -c archive_mode=on -c archive_command='$W/bin/pgbackrest-archive.sh %p' -c archive_timeout=$ARCHIVE_TIMEOUT -c wal_compression=on -c max_wal_size=1GB" start >/dev/null
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
    in_backup() { $DC exec -T backup "$@"; }
    BACKUP_SH="in_backup sh /usr/local/bin/backup.sh"
    DRILL="sh $SCRIPT_DIR/restore-drill.sh"
    PITR_RESTORE_SH="sh $SCRIPT_DIR/pitr-restore.sh"
    MIG_HOST=127.0.0.1; MIG_PORT=$PG_PORT; MIG_PASS=$DB_PASS
    SMOKE_PORT=${PITR_CI_RESTORE_PORT:-55499}
    target_down() { $DC stop minio; }
    target_up() { $DC start minio; }
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
[ "$(live_sql "SELECT count(*) FROM restore_drills WHERE status = 'ok'")" -ge 1 ] || die "no restore_drills row"
RESTORE_SECONDS=$(val RESTORE_SECONDS); RECOVERY_POINT=$(val RECOVERY_POINT); DATA_LOSS_SECONDS=$(val DATA_LOSS_SECONDS)
summary "restore to T: A+B present, C absent, probe token present, smoke: $(val CHECK_app_smoke)"
summary "RESTORE_SECONDS=$RESTORE_SECONDS"
summary "RECOVERY_POINT=$RECOVERY_POINT (T = $T)"
summary "DATA_LOSS_SECONDS=$DATA_LOSS_SECONDS"
rm -f "$out"

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
in_backup sh -c 'true' >/dev/null 2>&1 || true

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
