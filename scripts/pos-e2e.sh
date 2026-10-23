#!/usr/bin/env bash
# P3-33: the POS stack end-to-end test against the REAL backend, in one command:
#
#   scripts/pos-e2e.sh                       # start Laravel + Reverb on a fresh database, run the tests, stop everything
#   scripts/pos-e2e.sh --keep                # leave the servers running afterwards (stop them with: scripts/pos-e2e.sh --stop)
#   scripts/pos-e2e.sh -- test/02_telegram_order_test.dart    # arguments after `--` go to `dart test`
#
# What it starts (all on 127.0.0.1, free high ports, so it does not meet a dev server or the e2e-stack servers):
#   Laravel API   (php artisan serve, APP_ENV=e2e)   $POS_E2E_BACKEND_PORT  default 18200   on a Postgres database it WIPES and seeds
#   Reverb        (php artisan reverb:start)         $POS_E2E_REVERB_PORT   default 18280
# then runs e2e/pos (`dart test`): the choyxona Thursday evening (shift, booking with a deposit, ten rooms served by two
# waiters and the cashier, split bills, every tender, SALE/ADVANCE/CREDIT/REFUND receipts, X and Z), a Telegram order
# accepted at the till, the real-time channel and a version conflict. The apps' data layer (pos_data) is the only client.
#
# Needs: PHP 8.3 with pdo_pgsql and `composer install` done in backend/, the Dart workspace resolved (`flutter pub get`
# at the repository root) with `dart` on PATH, and a reachable Postgres. Redis is not needed: the stack uses the database
# for the cache and the sync queue.
#
# Database: POS_E2E_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD (127.0.0.1, 5432, rizqim_pos_e2e, rizqim, rizqim). It is
# created when the role may, and emptied (migrate:fresh) on every run, so the name must contain "e2e" or "testing".
# Before wiping, the script asks Laravel itself which environment and database it is on (the stack keeps its own
# config/route/event caches under e2e/pos/.run, so a developer's cached backend/bootstrap/cache/config.php is never
# read) and refuses unless that is APP_ENV=e2e on exactly this database.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUN="$ROOT/e2e/pos/.run"
PIDS="$RUN/pids"

DB_HOST="${POS_E2E_DB_HOST:-127.0.0.1}"
DB_PORT="${POS_E2E_DB_PORT:-5432}"
DB_NAME="${POS_E2E_DB_DATABASE:-rizqim_pos_e2e}"
DB_USER="${POS_E2E_DB_USERNAME:-rizqim}"
DB_PASS="${POS_E2E_DB_PASSWORD:-rizqim}"

REVERB_KEY="pos-e2e-reverb-key"

KEEP=0
STOP_ONLY=0
TEST_ARGS=()
while [ $# -gt 0 ]; do
  case "$1" in
    --keep) KEEP=1 ;;
    --stop) STOP_ONLY=1 ;;
    --) shift; TEST_ARGS=("$@"); break ;;
    -h | --help) sed -n '2,25p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unknown argument: $1 (see --help)" >&2; exit 2 ;;
  esac
  shift
done

log() { printf '\033[1;34m[pos-e2e]\033[0m %s\n' "$*"; }
die() { printf '\033[1;31m[pos-e2e]\033[0m %s\n' "$*" >&2; exit 1; }

stop_all() {
  [ -f "$PIDS" ] || return 0
  while read -r pid; do
    [ -n "$pid" ] || continue
    # every server was started with setsid: its pid is its process group, which takes the workers down with it
    kill -- "-$pid" 2> /dev/null || kill "$pid" 2> /dev/null || true
  done < "$PIDS"
  rm -f "$PIDS"
}

if [ "$STOP_ONLY" = 1 ]; then
  stop_all
  log "stopped"
  exit 0
fi

# --- Checks ---------------------------------------------------------------------------------------------------------

command -v php > /dev/null || die "php is not installed"
command -v dart > /dev/null || die "dart is not on PATH (Flutter's bin directory has it)"
command -v curl > /dev/null || die "curl is not installed"
[ -f "$ROOT/backend/vendor/autoload.php" ] || die "run: cd backend && composer install"
[ -f "$ROOT/.dart_tool/package_config.json" ] || die "run: flutter pub get (at the repository root)"

case "$DB_NAME" in
  *e2e* | *testing*) ;;
  *) die "refusing to wipe database '$DB_NAME': its name must contain 'e2e' or 'testing' (POS_E2E_DB_DATABASE)" ;;
esac

port_in_use() { (exec 3<> "/dev/tcp/127.0.0.1/$1") 2> /dev/null; }

# The default when it is free, else the next free one (two stacks, or a dev server, can be up at the same time)
free_port() { # preferred
  local port="$1" tries=0
  while port_in_use "$port"; do
    port=$((port + 1))
    tries=$((tries + 1))
    [ "$tries" -lt 200 ] || die "no free port near $1"
  done
  echo "$port"
}

BACKEND_PORT="${POS_E2E_BACKEND_PORT:-$(free_port 18200)}"
REVERB_PORT="${POS_E2E_REVERB_PORT:-$(free_port $((BACKEND_PORT == 18280 ? 18281 : 18280)))}"
for port in "$BACKEND_PORT" "$REVERB_PORT"; do
  if port_in_use "$port"; then
    die "port $port is already in use (another stack still running? scripts/pos-e2e.sh --stop)"
  fi
done

mkdir -p "$RUN"
: > "$PIDS"
trap 'if [ "$KEEP" = 1 ]; then log "servers left running (stop: scripts/pos-e2e.sh --stop)"; else stop_all; fi' EXIT
trap 'exit 130' INT TERM

wait_for() { # name url seconds
  local name="$1" url="$2" seconds="$3" i
  for ((i = 0; i < seconds * 2; i++)); do
    if curl -fsS -o /dev/null "$url" 2> /dev/null; then return 0; fi
    sleep 0.5
  done
  echo "--- last lines of $RUN/$name.log" >&2
  tail -n 30 "$RUN/$name.log" >&2 || true
  die "$name did not come up at $url within ${seconds}s"
}

start() { # name command...  (own session, so the whole process group can be stopped; output to .run/<name>.log)
  local name="$1"
  shift
  setsid "$@" > "$RUN/$name.log" 2>&1 &
  echo "$!" >> "$PIDS"
}

# --- Backend: environment, database, seed ----------------------------------------------------------------------------

# Everything is passed as environment variables, which win over backend/.env (it may be a developer's own)
BACKEND_ENV=(
  APP_ENV=e2e
  APP_DEBUG=false
  APP_URL="http://127.0.0.1:$BACKEND_PORT"
  APP_KEY="base64:cG9zLWUyZS1rZXktZm9yLXRoZS10ZXN0LXN0YWNrISE="
  DB_CONNECTION=pgsql DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_DATABASE="$DB_NAME" DB_USERNAME="$DB_USER" DB_PASSWORD="$DB_PASS" DB_URL=
  # No Redis: cache (OTP, rate limits, selection tickets) in the database, events published at once (sync queue)
  CACHE_STORE=database QUEUE_CONNECTION=sync SESSION_DRIVER=array
  SMS_DRIVER=log LOG_CHANNEL=stderr LOG_LEVEL=info
  BROADCAST_CONNECTION=reverb REVERB_APP_ID=pos-e2e REVERB_APP_KEY="$REVERB_KEY" REVERB_APP_SECRET=pos-e2e-reverb-secret
  REVERB_HOST=127.0.0.1 REVERB_PORT="$REVERB_PORT" REVERB_SCHEME=http
  REVERB_SERVER_HOST=127.0.0.1 REVERB_SERVER_PORT="$REVERB_PORT" REVERB_ALLOWED_ORIGINS='*'
  TELEGRAM_ALLOW_MOCK_AUTH=false
  E2E_POS_SEED_FILE="$RUN/seed.json"
  # The cook's polls, the waiters' and the till's calls arrive together: more than one PHP worker (php artisan serve
  # honours it only with --no-reload, below)
  PHP_CLI_SERVER_WORKERS=6
  # The stack's own config, route and event caches. A developer's cached backend/bootstrap/cache/config.php would make
  # Laravel ignore every variable above, and migrate:fresh would then wipe the database of their .env
  APP_CONFIG_CACHE="$RUN/cache-config.php" APP_ROUTES_CACHE="$RUN/cache-routes.php" APP_EVENTS_CACHE="$RUN/cache-events.php"
)
artisan() { (cd "$ROOT/backend" && env "${BACKEND_ENV[@]}" php artisan "$@"); }

if command -v psql > /dev/null && command -v pg_isready > /dev/null; then
  pg_isready -q -h "$DB_HOST" -p "$DB_PORT" || die "Postgres is not reachable at $DB_HOST:$DB_PORT"
  if ! PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c 'select 1' > /dev/null 2>&1; then
    log "creating database $DB_NAME"
    PGPASSWORD="$DB_PASS" createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "$DB_NAME" \
      || die "cannot create database $DB_NAME: create it (or set POS_E2E_DB_DATABASE to an existing test database)"
  fi
fi

# Clear the stack's own caches (never the developer's), then ask Laravel what it is really connected to: the
# environment, the configured database and the database the server says this connection is on. Refuse to wipe anything
# else, whatever the reason (a cached config, a DB_URL, an env file that overrides the variables)
artisan config:clear > /dev/null 2>&1 || true
artisan route:clear > /dev/null 2>&1 || true
artisan event:clear > /dev/null 2>&1 || true
rm -f "$RUN/cache-config.php" "$RUN/cache-routes.php" "$RUN/cache-events.php" "$RUN/seed.json"
# shellcheck disable=SC2016 # PHP code: the $ are PHP's
effective="$(cd "$ROOT/backend" && env "${BACKEND_ENV[@]}" php -r '
  require "vendor/autoload.php";
  $app = require "bootstrap/app.php";
  $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  $db = Illuminate\Support\Facades\DB::connection("pgsql");
  echo $app->environment(), "|", config("database.default"), "|", config("database.connections.pgsql.database"), "|",
    $db->selectOne("select current_database() as name")->name, PHP_EOL;
' 2> "$RUN/effective-db.log")" || { tail -n 20 "$RUN/effective-db.log" >&2; die "cannot ask Laravel which database it uses"; }
IFS='|' read -r eff_env eff_default eff_config eff_server <<< "$effective"
if [ "$eff_env" != e2e ] || [ "$eff_default" != pgsql ] || [ "$eff_config" != "$DB_NAME" ] || [ "$eff_server" != "$DB_NAME" ]; then
  die "refusing to wipe: Laravel runs as APP_ENV=$eff_env on connection $eff_default, database '$eff_config' (server: '$eff_server'), expected e2e on '$DB_NAME'. A cached config or DB_URL? See backend/bootstrap/cache."
fi
case "$eff_server" in
  *e2e* | *testing*) ;;
  *) die "refusing to wipe database '$eff_server': its name must contain 'e2e' or 'testing'" ;;
esac

log "migrating and seeding $DB_NAME (choyxona: PosStackSeeder)"
artisan migrate:fresh --force --no-interaction > "$RUN/migrate.log" 2>&1 || { tail -n 30 "$RUN/migrate.log" >&2; die "migrate:fresh failed"; }
artisan db:seed --class=PosStackSeeder --force --no-interaction > "$RUN/seed.log" 2>&1 || { tail -n 30 "$RUN/seed.log" >&2; die "seeding failed"; }
[ -s "$RUN/seed.json" ] || die "the seeder wrote no $RUN/seed.json"

# --- Servers ---------------------------------------------------------------------------------------------------------

log "starting the backend (:$BACKEND_PORT) and Reverb (:$REVERB_PORT)"
(cd "$ROOT/backend" && start backend env "${BACKEND_ENV[@]}" php artisan serve --host=127.0.0.1 --port="$BACKEND_PORT" --no-reload)
(cd "$ROOT/backend" && start reverb env "${BACKEND_ENV[@]}" php artisan reverb:start --host=127.0.0.1 --port="$REVERB_PORT")

wait_for backend "http://127.0.0.1:$BACKEND_PORT/api/v1/health" 60
# Reverb answers 404 on a plain GET but is up when it answers at all
wait_for_reverb() {
  local i
  for ((i = 0; i < 60; i++)); do
    if curl -sS -o /dev/null "http://127.0.0.1:$REVERB_PORT/" 2> /dev/null; then return 0; fi
    sleep 0.5
  done
  tail -n 30 "$RUN/reverb.log" >&2 || true
  die "Reverb did not come up on :$REVERB_PORT"
}
wait_for_reverb

# --- Tests -----------------------------------------------------------------------------------------------------------

log "running the tests"
status=0
(cd "$ROOT/e2e/pos" && env \
  POS_E2E_API_URL="http://127.0.0.1:$BACKEND_PORT/api" POS_E2E_REVERB_URL="ws://127.0.0.1:$REVERB_PORT" \
  POS_E2E_REVERB_KEY="$REVERB_KEY" POS_E2E_SEED_FILE="$RUN/seed.json" POS_E2E_LOG_FILE="$RUN/backend.log" \
  dart test ${TEST_ARGS[@]+"${TEST_ARGS[@]}"}) || status=$?

if [ "$status" -ne 0 ]; then
  log "FAILED (exit $status). The servers' logs: $RUN/backend.log, $RUN/reverb.log"
fi
exit "$status"
