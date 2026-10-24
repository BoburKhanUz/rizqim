#!/usr/bin/env bash
# Checks of scripts/pos-e2e.sh that need no backend, database or Dart (run: scripts/pos-e2e.test.sh):
#   * a run refuses to start while the pid file lists a live process (servers left by --keep), and leaves them alone;
#   * a pid file of processes that are gone does not block a run;
#   * --stop stops what the pid file lists and removes it.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT="$ROOT/scripts/pos-e2e.sh"
TMP="$(mktemp -d)"
live=""
cleanup() {
  if [ -n "$live" ]; then kill "$live" 2> /dev/null || true; fi
  rm -rf "$TMP"
}
trap cleanup EXIT

fail() {
  printf 'FAIL: %s\n' "$*" >&2
  exit 1
}

# A database name the script never wipes: a run that gets past the pid check stops at the name check at the latest
run() { POS_E2E_RUN_DIR="$TMP" POS_E2E_DB_DATABASE=not_a_test_database "$SCRIPT" "$@" 2>&1; }

# 1. A live server from an earlier --keep run
sleep 300 &
live=$!
printf '%s\n' "$live" > "$TMP/pids"
if out="$(run)"; then fail "started while pid $live is alive"; fi
grep -q "still running (pids: $live)" <<< "$out" || fail "no 'still running' refusal: $out"
[ "$(cat "$TMP/pids")" = "$live" ] || fail "the pid file was changed"
kill -0 "$live" 2> /dev/null || fail "the running server was stopped"

# 2. --stop stops it and removes the pid file
run --stop > /dev/null || fail "--stop failed"
for _ in 1 2 3 4 5 6 7 8 9 10; do
  kill -0 "$live" 2> /dev/null || break
  sleep 0.2
done
if kill -0 "$live" 2> /dev/null; then fail "--stop left pid $live running"; fi
[ ! -e "$TMP/pids" ] || fail "--stop kept the pid file"
live=""

# 3. Pids of processes that are gone (a crashed run) do not block
sleep 0 &
gone=$!
wait "$gone" || true
printf '%s\n\n' "$gone" > "$TMP/pids"
if out="$(run)"; then fail "the run did not stop at the database name check"; fi
if grep -q "still running" <<< "$out"; then fail "a dead pid blocked the run: $out"; fi

echo "pos-e2e.sh checks passed"
