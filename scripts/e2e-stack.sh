#!/usr/bin/env bash
# P2-31: the cross-app end-to-end test against the REAL stack, in one command:
#
#   scripts/e2e-stack.sh                 # build, start everything, run the tests, stop everything
#   scripts/e2e-stack.sh --keep          # leave the servers running afterwards (stop them with: scripts/e2e-stack.sh --stop)
#   scripts/e2e-stack.sh -- --headed     # arguments after `--` go to `playwright test`
#
# What it starts (all on 127.0.0.1, high ports, so it does not meet a dev server on 8000/3000/5173):
#   Laravel API   (php artisan serve, APP_ENV=e2e)     $E2E_BACKEND_PORT  18000   on a Postgres database it WIPES and seeds
#   Reverb        (php artisan reverb:start)           $E2E_REVERB_PORT   18080
#   Partner web   (next start, the production build)   $E2E_PARTNER_PORT  13000
#   Mini App      (vite preview of a production build, /api proxied to the backend)  $E2E_MINIAPP_PORT 14173
#   Control center (platform/admin, next start, the production build)   $E2E_ADMIN_PORT    13300
# then runs Playwright from e2e/stack (customer order -> partner web board -> kitchen bump -> Mini App manager screen; and the
# control center journey, tests/admin-journey.spec.ts: the first superadmin made by `php artisan rizqim:admin:create`
# accepts the invite link, sets up 2FA with TOTP codes the test computes itself, invites and re-roles a second member
# behind step-up, and finds it in the audit log and the search).
#
# Needs: PHP 8.3 with pdo_pgsql and `composer install` done in backend/, Node 22 with `npm ci` done in partner/web,
# platform/admin, customer/miniapp, e2e/stack and packages/ts/api-client (built: npm run build), a reachable Postgres, and a Chromium for
# Playwright (`npx playwright install chromium` in e2e/stack, or PW_CHROMIUM_PATH=/path/to/chrome). Redis is not needed:
# the stack uses the database for cache and the sync queue.
#
# Real-time: the test expects each change over the Reverb socket within 8 s (the boards poll only every 60 s while their socket
# is up). If it does not arrive in time it falls back to waiting for the poll, prints "[realtime-fallback]" and records an
# annotation, so a slow socket never fails the journey but is never silent either. The socket itself is asserted strictly.
#
# Database: E2E_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD (127.0.0.1, 5432, rizqim_e2e, rizqim, rizqim). The database is
# created when the role may, and is emptied (migrate:fresh) on every run, so the name must contain "e2e" or "testing".
# Before wiping, the script asks Laravel itself which environment and database it is on (the stack keeps its own
# config/route/event caches under e2e/stack/.run, so a developer's cached backend/bootstrap/cache/config.php is never
# read) and refuses unless that is APP_ENV=e2e on exactly this database.
# Other variables: E2E_SKIP_BUILD=1 reuses the builds of the last run; E2E_BACKEND_PORT, E2E_REVERB_PORT,
# E2E_PARTNER_PORT, E2E_MINIAPP_PORT, E2E_ADMIN_PORT move the servers. The control center test waits for the next 30 s TOTP time
# step before each sensitive action (the backend takes every code once), so it needs about two minutes of wall clock.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUN="$ROOT/e2e/stack/.run"
PIDS="$RUN/pids"

BACKEND_PORT="${E2E_BACKEND_PORT:-18000}"
REVERB_PORT="${E2E_REVERB_PORT:-18080}"
PARTNER_PORT="${E2E_PARTNER_PORT:-13000}"
MINIAPP_PORT="${E2E_MINIAPP_PORT:-14173}"
ADMIN_PORT="${E2E_ADMIN_PORT:-13300}"

DB_HOST="${E2E_DB_HOST:-127.0.0.1}"
DB_PORT="${E2E_DB_PORT:-5432}"
DB_NAME="${E2E_DB_DATABASE:-rizqim_e2e}"
DB_USER="${E2E_DB_USERNAME:-rizqim}"
DB_PASS="${E2E_DB_PASSWORD:-rizqim}"

# The Telegram bot of the stack is made up: the tests sign the Mini App's initData with this token themselves
BOT_TOKEN="123456:E2E-TEST-TOKEN"
BOT_USERNAME="e2e_bot"
REVERB_KEY="e2e-reverb-key"

KEEP=0
STOP_ONLY=0
PLAYWRIGHT_ARGS=()
while [ $# -gt 0 ]; do
  case "$1" in
    --keep) KEEP=1 ;;
    --stop) STOP_ONLY=1 ;;
    --) shift; PLAYWRIGHT_ARGS=("$@"); break ;;
    -h | --help) sed -n '2,30p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "Unknown argument: $1 (see --help)" >&2; exit 2 ;;
  esac
  shift
done

log() { printf '\033[1;34m[e2e-stack]\033[0m %s\n' "$*"; }
die() { printf '\033[1;31m[e2e-stack]\033[0m %s\n' "$*" >&2; exit 1; }

stop_all() {
  [ -f "$PIDS" ] || return 0
  while read -r pid; do
    [ -n "$pid" ] || continue
    # every server was started with setsid: its pid is its process group, which takes the workers down with it
    kill -- "-$pid" 2>/dev/null || kill "$pid" 2>/dev/null || true
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
command -v node > /dev/null || die "node is not installed"
command -v curl > /dev/null || die "curl is not installed"
[ -f "$ROOT/backend/vendor/autoload.php" ] || die "run: cd backend && composer install"
for dir in partner/web platform/admin customer/miniapp e2e/stack; do
  [ -d "$ROOT/$dir/node_modules" ] || die "run: cd $dir && npm ci"
done
[ -f "$ROOT/packages/ts/api-client/dist/index.js" ] || die "run: cd packages/ts/api-client && npm ci && npm run build"

case "$DB_NAME" in
  *e2e* | *testing*) ;;
  *) die "refusing to wipe database '$DB_NAME': its name must contain 'e2e' or 'testing' (E2E_DB_DATABASE)" ;;
esac

for port in "$BACKEND_PORT" "$REVERB_PORT" "$PARTNER_PORT" "$MINIAPP_PORT" "$ADMIN_PORT"; do
  if (exec 3<> "/dev/tcp/127.0.0.1/$port") 2> /dev/null; then
    die "port $port is already in use (another stack still running? scripts/e2e-stack.sh --stop)"
  fi
done

mkdir -p "$RUN"
: > "$PIDS"
trap 'if [ "$KEEP" = 1 ]; then log "servers left running (stop: scripts/e2e-stack.sh --stop)"; else stop_all; fi' EXIT
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
  APP_KEY="base64:ZTJlLWtleS1mb3ItdGhlLXRlc3Qtc3RhY2stb25seSE="
  DB_CONNECTION=pgsql DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_DATABASE="$DB_NAME" DB_USERNAME="$DB_USER" DB_PASSWORD="$DB_PASS" DB_URL=
  # No Redis: cache (OTP, rate limits, selection tickets) in the database, events published at once (sync queue)
  CACHE_STORE=database QUEUE_CONNECTION=sync SESSION_DRIVER=array
  # The SMS driver logs "SMS Mocked to <phone>: ... <code>"; the log goes to stderr, captured in .run/backend.log
  SMS_DRIVER=log LOG_CHANNEL=stderr LOG_LEVEL=info
  BROADCAST_CONNECTION=reverb REVERB_APP_ID=e2e REVERB_APP_KEY="$REVERB_KEY" REVERB_APP_SECRET=e2e-reverb-secret
  REVERB_HOST=127.0.0.1 REVERB_PORT="$REVERB_PORT" REVERB_SCHEME=http
  REVERB_SERVER_HOST=127.0.0.1 REVERB_SERVER_PORT="$REVERB_PORT" REVERB_ALLOWED_ORIGINS='*'
  TELEGRAM_BOT_TOKEN="$BOT_TOKEN" TELEGRAM_BOT_USERNAME="$BOT_USERNAME" TELEGRAM_ALLOW_MOCK_AUTH=false
  # The partner web calls the API straight from the browser: its origin must be allowed
  CORS_ALLOWED_ORIGINS="http://127.0.0.1:$PARTNER_PORT,http://127.0.0.1:$MINIAPP_PORT"
  E2E_SEED_FILE="$RUN/seed.json"
  # The control center: invite links point to it (the browser never calls the backend itself, the app's server does: no CORS)
  ADMIN_APP_URL="http://127.0.0.1:$ADMIN_PORT"
  # The Reverb socket, the board's polls and the tests' own calls arrive together: more than one PHP worker (php artisan
  # serve honours it only with --no-reload, below)
  PHP_CLI_SERVER_WORKERS=4
  # P2-33: the stack's own config, route and event caches. A developer's cached backend/bootstrap/cache/config.php would
  # make Laravel ignore every variable above, and migrate:fresh would then wipe the database of their .env
  APP_CONFIG_CACHE="$RUN/cache-config.php" APP_ROUTES_CACHE="$RUN/cache-routes.php" APP_EVENTS_CACHE="$RUN/cache-events.php"
)
artisan() { (cd "$ROOT/backend" && env "${BACKEND_ENV[@]}" php artisan "$@"); }

if command -v psql > /dev/null && command -v pg_isready > /dev/null; then
  pg_isready -q -h "$DB_HOST" -p "$DB_PORT" || die "Postgres is not reachable at $DB_HOST:$DB_PORT"
  if ! PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -c 'select 1' > /dev/null 2>&1; then
    log "creating database $DB_NAME"
    PGPASSWORD="$DB_PASS" createdb -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "$DB_NAME" \
      || die "cannot create database $DB_NAME: create it (or set E2E_DB_DATABASE to an existing test database)"
  fi
fi

# P2-33: clear the stack's own caches (never the developer's), then ask Laravel what it is really connected to: the
# environment, the configured database and the database the server says this connection is on. Refuse to wipe anything
# else, whatever the reason (a cached config, a DB_URL, an env file that overrides the variables)
artisan config:clear > /dev/null 2>&1 || true
artisan route:clear > /dev/null 2>&1 || true
artisan event:clear > /dev/null 2>&1 || true
rm -f "$RUN/cache-config.php" "$RUN/cache-routes.php" "$RUN/cache-events.php"
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

log "migrating and seeding $DB_NAME"
artisan migrate:fresh --force --no-interaction > "$RUN/migrate.log" 2>&1 || { tail -n 30 "$RUN/migrate.log" >&2; die "migrate:fresh failed"; }
artisan db:seed --class=E2EStackSeeder --force --no-interaction > "$RUN/seed.log" 2>&1 || { tail -n 30 "$RUN/seed.log" >&2; die "seeding failed"; }

# The first superadmin of the control center, the way the operators make it (docs/OPERATIONS.md): the command prints the one-time
# invite link; the test opens it in the browser
log "creating the first superadmin of the control center"
artisan rizqim:admin:create e2e-admin@rizqim.test "E2E Superadmin" > "$RUN/admin-create.log" 2>&1 || { tail -n 20 "$RUN/admin-create.log" >&2; die "rizqim:admin:create failed"; }
grep -Eo "http://127\.0\.0\.1:$ADMIN_PORT/invite/[A-Za-z0-9_-]+" "$RUN/admin-create.log" | head -n 1 > "$RUN/admin-invite.url"
[ -s "$RUN/admin-invite.url" ] || { cat "$RUN/admin-create.log" >&2; die "no invite link in the output of rizqim:admin:create"; }

# --- Builds ----------------------------------------------------------------------------------------------------------

if [ "${E2E_SKIP_BUILD:-0}" != 1 ] || [ ! -d "$ROOT/partner/web/.next" ] || [ ! -d "$ROOT/platform/admin/.next" ] || [ ! -d "$RUN/miniapp-dist" ]; then
  log "building the partner web"
  (cd "$ROOT/partner/web" && npm run build > "$RUN/partner-build.log" 2>&1) || { tail -n 30 "$RUN/partner-build.log" >&2; die "partner web build failed"; }

  log "building the control center"
  (cd "$ROOT/platform/admin" && npm run build > "$RUN/admin-build.log" 2>&1) || { tail -n 30 "$RUN/admin-build.log" >&2; die "control center build failed"; }

  # The Mini App is built for this stack: API on its own origin (proxied), Reverb straight on its port. They are build
  # variables (Vite), so the build goes to .run and never touches customer/miniapp/dist.
  log "building the Mini App"
  (cd "$ROOT/customer/miniapp" && env VITE_API_URL=/api/v1 VITE_BOT_USERNAME="$BOT_USERNAME" VITE_SENTRY_DSN= VITE_APP_DEBUG= \
    VITE_REVERB_APP_KEY="$REVERB_KEY" VITE_REVERB_HOST=127.0.0.1 VITE_REVERB_PORT="$REVERB_PORT" VITE_REVERB_SCHEME=http \
    npx vite build --outDir "$RUN/miniapp-dist" --emptyOutDir > "$RUN/miniapp-build.log" 2>&1) \
    || { tail -n 30 "$RUN/miniapp-build.log" >&2; die "Mini App build failed"; }
fi

# --- Servers ---------------------------------------------------------------------------------------------------------

log "starting the backend (:$BACKEND_PORT) and Reverb (:$REVERB_PORT)"
(cd "$ROOT/backend" && start backend env "${BACKEND_ENV[@]}" php artisan serve --host=127.0.0.1 --port="$BACKEND_PORT" --no-reload)
(cd "$ROOT/backend" && start reverb env "${BACKEND_ENV[@]}" php artisan reverb:start --host=127.0.0.1 --port="$REVERB_PORT")

log "starting the partner web (:$PARTNER_PORT), the control center (:$ADMIN_PORT) and the Mini App (:$MINIAPP_PORT)"
(cd "$ROOT/partner/web" && start partner-web env \
  API_BASE_URL="http://127.0.0.1:$BACKEND_PORT/api" PUBLIC_API_BASE_URL="http://127.0.0.1:$BACKEND_PORT/api" \
  SESSION_COOKIE_SECURE=false REVERB_APP_KEY="$REVERB_KEY" REVERB_HOST=127.0.0.1 REVERB_PORT="$REVERB_PORT" REVERB_SCHEME=http \
  npx next start -H 127.0.0.1 -p "$PARTNER_PORT")
(cd "$ROOT/platform/admin" && start admin env \
  API_BASE_URL="http://127.0.0.1:$BACKEND_PORT/api" SESSION_COOKIE_SECURE=false \
  npx next start -H 127.0.0.1 -p "$ADMIN_PORT")
(cd "$ROOT/customer/miniapp" && start miniapp env API_PROXY_TARGET="http://127.0.0.1:$BACKEND_PORT" \
  npx vite preview --outDir "$RUN/miniapp-dist" --host 127.0.0.1 --port "$MINIAPP_PORT" --strictPort)

wait_for backend "http://127.0.0.1:$BACKEND_PORT/api/v1/health" 60
wait_for partner-web "http://127.0.0.1:$PARTNER_PORT/login" 60
wait_for admin "http://127.0.0.1:$ADMIN_PORT/login" 60
wait_for miniapp "http://127.0.0.1:$MINIAPP_PORT/" 60
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
(cd "$ROOT/e2e/stack" && env \
  E2E_BACKEND_URL="http://127.0.0.1:$BACKEND_PORT" E2E_PARTNER_URL="http://127.0.0.1:$PARTNER_PORT" \
  E2E_MINIAPP_URL="http://127.0.0.1:$MINIAPP_PORT" E2E_ADMIN_URL="http://127.0.0.1:$ADMIN_PORT" E2E_ADMIN_INVITE_FILE="$RUN/admin-invite.url" E2E_LOG_FILE="$RUN/backend.log" E2E_SEED_FILE="$RUN/seed.json" \
  E2E_BOT_TOKEN="$BOT_TOKEN" E2E_BOT_USERNAME="$BOT_USERNAME" \
  npx playwright test ${PLAYWRIGHT_ARGS[@]+"${PLAYWRIGHT_ARGS[@]}"}) || status=$?

if [ "$status" -ne 0 ]; then
  log "FAILED (exit $status). Logs: $RUN/*.log, Playwright report: e2e/stack/playwright-report"
fi
exit "$status"
