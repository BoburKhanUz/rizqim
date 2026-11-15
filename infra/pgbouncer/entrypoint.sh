#!/bin/sh
# Writes pgbouncer.ini and the auth file from the environment, then runs PgBouncer in the foreground (logs to stderr).
# Transaction pooling: a server connection is lent to a client for one transaction only, so php-fpm workers (one short
# client connection per request) share PGBOUNCER_MAX_DB_CONNECTIONS real Postgres connections. Sizing and what goes
# through the pooler: docs/OPERATIONS.md ("Bazaga ulanishlar puli").
set -eu

: "${DB_HOST:=postgres}"
: "${DB_PORT:=5432}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"
: "${PGBOUNCER_PORT:=6432}"
: "${PGBOUNCER_POOL_SIZE:=20}"
: "${PGBOUNCER_RESERVE_POOL_SIZE:=5}"
: "${PGBOUNCER_MAX_DB_CONNECTIONS:=30}"
: "${PGBOUNCER_MAX_CLIENT_CONN:=1000}"
: "${PGBOUNCER_QUERY_WAIT_TIMEOUT:=30}"

value=
for name in PGBOUNCER_PORT PGBOUNCER_POOL_SIZE PGBOUNCER_RESERVE_POOL_SIZE PGBOUNCER_MAX_DB_CONNECTIONS PGBOUNCER_MAX_CLIENT_CONN PGBOUNCER_QUERY_WAIT_TIMEOUT DB_PORT; do
    eval "value=\${$name}"
    case "$value" in
        '' | *[!0-9]*) echo "XATO: $name butun son bo'lishi kerak (hozir: '$value')." >&2; exit 1 ;;
    esac
done
case "$DB_DATABASE$DB_USERNAME$DB_HOST" in
    *[!A-Za-z0-9_.-]*) echo "XATO: DB_DATABASE, DB_USERNAME va DB_HOST faqat harf, raqam, '_', '.', '-' dan iborat bo'lsin." >&2; exit 1 ;;
esac

# PGBOUNCER_RUN_DIR: where the generated files go (scripts/scale-test/pgbouncer.sh runs this script outside Docker)
RUN_DIR=${PGBOUNCER_RUN_DIR:-/tmp/pgbouncer}
mkdir -p "$RUN_DIR"
chmod 700 "$RUN_DIR"
umask 077

# Auth file: "user" "password"; a double quote inside the password is written twice (PgBouncer's quoting rule)
printf '"%s" "%s"\n' "$DB_USERNAME" "$(printf '%s' "$DB_PASSWORD" | sed 's/"/""/g')" > "$RUN_DIR/userlist.txt"

cat > "$RUN_DIR/pgbouncer.ini" <<INI
[databases]
; Only the application's database is served; the server login uses the same user and password as the client
$DB_DATABASE = host=$DB_HOST port=$DB_PORT dbname=$DB_DATABASE

[pgbouncer]
; Not published outside the compose network (docker-compose.yml has no ports: for this service)
listen_addr = ${PGBOUNCER_LISTEN_ADDR:-0.0.0.0}
listen_port = $PGBOUNCER_PORT
unix_socket_dir =
; Client (php-fpm) -> PgBouncer: md5 challenge on the private compose network. SCRAM here would cost every request the
; ~20 ms handshake this pooler exists to remove (measured, docs/OPERATIONS.md). PgBouncer -> Postgres stays SCRAM,
; once per server connection.
auth_type = md5
auth_file = $RUN_DIR/userlist.txt
pool_mode = transaction
default_pool_size = $PGBOUNCER_POOL_SIZE
reserve_pool_size = $PGBOUNCER_RESERVE_POOL_SIZE
reserve_pool_timeout = 3
max_db_connections = $PGBOUNCER_MAX_DB_CONNECTIONS
max_client_conn = $PGBOUNCER_MAX_CLIENT_CONN
; A request waiting longer than this for a free server connection gets an error instead of hanging
query_wait_timeout = $PGBOUNCER_QUERY_WAIT_TIMEOUT
server_idle_timeout = 600
server_lifetime = 3600
; PHP disables named prepared statements in this mode (config/database.php); 0 makes a regression fail loudly
max_prepared_statements = 0
ignore_startup_parameters = extra_float_digits
; Every php-fpm request connects and disconnects: per-connection log lines would flood the log
log_connections = 0
log_disconnections = 0
stats_period = 60
; Read-only SHOW POOLS / SHOW STATS for the application user (docs/OPERATIONS.md)
stats_users = $DB_USERNAME
INI

exec pgbouncer "$RUN_DIR/pgbouncer.ini"
