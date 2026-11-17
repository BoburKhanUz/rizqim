#!/usr/bin/env bash
# Rebuilds a development/agent container for this repo from nothing: system packages, the local Postgres role and
# test database, the Flutter SDK, PHP and Node dependencies. Idempotent; run as root from anywhere:
#
#   bash scripts/dev-setup.sh
#
# Written for the Ubuntu 24.04 cloud container (PHP 8.3, Node 22, Postgres 16 and Redis preinstalled). It is not used
# by CI or by the production images. Passwords here are the local test defaults that scripts/pos-e2e.sh and
# scripts/e2e-stack.sh expect (role rizqim / rizqim); they protect nothing.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FLUTTER_VERSION="$(grep -m1 -oE 'FLUTTER_VERSION: *[0-9.]+' "$ROOT/.github/workflows/ci.yml" | grep -oE '[0-9.]+$')"
log() { printf '[dev-setup] %s\n' "$*"; }

log "system packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq nginx php8.3-fpm php8.3-bcmath pgbouncer shellcheck postgresql-contrib-16 zip > /dev/null
# Installed for the load-test kit and the pool tests only: they start their own instances on their own ports.
service nginx stop > /dev/null 2>&1 || true
service php8.3-fpm stop > /dev/null 2>&1 || true
service pgbouncer stop > /dev/null 2>&1 || true

log "Postgres: statistics modules, role, test database"
PGCONF=/etc/postgresql/16/main/postgresql.conf
PRELOAD="shared_preload_libraries = 'pg_stat_statements,auto_explain'"
grep -qxF "$PRELOAD" "$PGCONF" || echo "$PRELOAD" >> "$PGCONF"
service postgresql restart > /dev/null
sudo -n -u postgres psql -qc "DO \$\$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rizqim') THEN CREATE ROLE rizqim LOGIN CREATEDB PASSWORD 'rizqim'; END IF; END \$\$"
sudo -n -u postgres psql -Atc "select 1 from pg_database where datname = 'testing'" | grep -q 1 \
  || sudo -n -u postgres createdb -O rizqim testing

if [ ! -x /opt/flutter/bin/flutter ]; then
  log "Flutter $FLUTTER_VERSION"
  curl -fsSL -o /tmp/flutter.tar.xz \
    "https://storage.googleapis.com/flutter_infra_release/releases/stable/linux/flutter_linux_${FLUTTER_VERSION}-stable.tar.xz" \
    && tar -xJf /tmp/flutter.tar.xz -C /opt
  rm -f /tmp/flutter.tar.xz
  git config --global --add safe.directory /opt/flutter
fi

log "PHP dependencies"
export COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_PROCESS_TIMEOUT=1200
cd "$ROOT/backend" || exit 1
# Where GitHub archive downloads are refused (the cloud proxy answers 403), Composer must clone instead, and a package
# that publishes archives only (phpstan/phpstan) is fetched with git and placed in Composer's download cache.
python3 - << 'PY' > /tmp/dist-only.txt
import json
lock = json.load(open('composer.lock'))
for p in lock['packages'] + lock['packages-dev']:
    if not p.get('source'):
        print(p['name'], p['dist']['reference'], p['dist']['url'])
PY
while read -r name ref url; do
  cache="$HOME/.cache/composer/files/$name"
  [ -f "$cache/$ref.zip" ] && continue
  if curl -fsSL -o /dev/null "$url"; then continue; fi
  work="$(mktemp -d)"
  git init -q "$work/src" && git -C "$work/src" remote add origin "https://github.com/$name.git" \
    && git -C "$work/src" fetch -q --depth 1 origin "$ref" && git -C "$work/src" checkout -q FETCH_HEAD \
    && rm -rf "$work/src/.git" && mv "$work/src" "$work/${name//\//-}-${ref:0:7}" \
    && (cd "$work" && zip -qr pkg.zip "${name//\//-}-${ref:0:7}") \
    && mkdir -p "$cache" && cp "$work/pkg.zip" "$cache/$ref.zip" \
    && cp "$work/pkg.zip" "$cache/$(printf %s "$url" | sha1sum | cut -d' ' -f1).zip"
  rm -rf "$work"
done < /tmp/dist-only.txt
rm -f /tmp/dist-only.txt
for attempt in 1 2 3; do
  composer install --no-interaction --no-progress -q && break
  composer install --no-interaction --no-progress -q --prefer-source && break
  log "composer install failed (attempt $attempt), retrying"
done
[ -f vendor/autoload.php ] || { log "composer install did not finish"; exit 1; }
if [ ! -f .env ]; then
  cp .env.example .env
  sed -i -e 's/^DB_USERNAME=.*/DB_USERNAME=rizqim/' -e 's/^DB_PASSWORD=.*/DB_PASSWORD=rizqim/' \
    -e 's/^DB_DATABASE=.*/DB_DATABASE=testing/' .env
  php artisan key:generate -q
fi

log "Node dependencies"
for dir in packages/ts/api-client packages/ts/staff customer/miniapp partner/web platform/admin e2e/stack; do
  (cd "$ROOT/$dir" && npm ci --no-audit --no-fund --loglevel=error) || { log "npm ci failed in $dir"; exit 1; }
done
(cd "$ROOT/packages/ts/api-client" && npm run build > /dev/null) || exit 1

log "Dart packages"
(cd "$ROOT" && PATH=/opt/flutter/bin:$PATH flutter pub get > /dev/null) || exit 1

log "done"
