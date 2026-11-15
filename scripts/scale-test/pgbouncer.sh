#!/bin/bash
# pgbouncer.sh start | stop | stats  -- PgBouncer in transaction mode in front of the local Postgres, configured by the
# same script as the compose service (infra/pgbouncer/entrypoint.sh), for the pooled connection mode of the web process:
#   ./pgbouncer.sh start && DB_POOLER=pgbouncer ./server.sh start      (or DB_POOLER=pgbouncer ./run.sh L probe load)
# Needs `apt-get install pgbouncer` (not a repo dependency). It runs as the postgres OS user (PgBouncer refuses root);
# its generated config, auth file and log live in $PGB_DIR, outside the repo (the auth file holds the DB password).
# Pool sizes: the PGBOUNCER_* variables of infra/pgbouncer/entrypoint.sh (compose defaults when unset).
# shellcheck disable=SC1091
source "$(dirname "$0")/env.sh"
PGB_DIR=${PGB_DIR:-/var/tmp/rizqim-scale-pgbouncer}
db_env() { grep "^$1=" "$BACKEND/.env" | cut -d= -f2- | tr -d '"'; }
case "$1" in
start)
  command -v pgbouncer >/dev/null || { echo "pgbouncer not found: apt-get install pgbouncer" >&2; exit 1; }
  sudo -n mkdir -p "$PGB_DIR" && sudo -n chown postgres "$PGB_DIR" && sudo -n chmod 700 "$PGB_DIR"
  "$0" stop >/dev/null 2>&1
  # shellcheck disable=SC2024 # the log is written by this user, into logs/
  sudo -n -u postgres env DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE="$DB_DATABASE" \
    DB_USERNAME="${DB_USERNAME:-$(db_env DB_USERNAME)}" DB_PASSWORD="${DB_PASSWORD:-$(db_env DB_PASSWORD)}" \
    PGBOUNCER_PORT="$DB_POOL_PORT" PGBOUNCER_LISTEN_ADDR=127.0.0.1 PGBOUNCER_RUN_DIR="$PGB_DIR" \
    PGBOUNCER_POOL_SIZE="${PGBOUNCER_POOL_SIZE:-20}" PGBOUNCER_RESERVE_POOL_SIZE="${PGBOUNCER_RESERVE_POOL_SIZE:-5}" \
    PGBOUNCER_MAX_DB_CONNECTIONS="${PGBOUNCER_MAX_DB_CONNECTIONS:-30}" PGBOUNCER_MAX_CLIENT_CONN="${PGBOUNCER_MAX_CLIENT_CONN:-1000}" \
    PGBOUNCER_QUERY_WAIT_TIMEOUT="${PGBOUNCER_QUERY_WAIT_TIMEOUT:-30}" \
    sh "$SC/../../infra/pgbouncer/entrypoint.sh" > "$SCALE_WORK/logs/pgbouncer.log" 2>&1 < /dev/null &
  for _ in $(seq 1 20); do (echo > "/dev/tcp/127.0.0.1/$DB_POOL_PORT") 2>/dev/null && break; sleep 0.25; done
  echo "pgbouncer (transaction mode) on 127.0.0.1:$DB_POOL_PORT -> $DB_DATABASE";;
stop)
  sudo -n pkill -f "pgbouncer $PGB_DIR/pgbouncer.ini"; echo stopped;;
stats)
  PGPASSWORD="${DB_PASSWORD:-$(db_env DB_PASSWORD)}" psql -h 127.0.0.1 -p "$DB_POOL_PORT" -U "${DB_USERNAME:-$(db_env DB_USERNAME)}" pgbouncer \
    -c 'show pools' -c 'show stats';;
*) echo "usage: $0 start|stop|stats" >&2; exit 1;;
esac
