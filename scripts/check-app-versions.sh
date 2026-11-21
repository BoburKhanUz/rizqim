#!/usr/bin/env bash
# M5 S5: every POS app's AppConfig.version / AppConfig.build must equal the `version:` line of its pubspec.yaml
# (`0.1.0+1` -> version 0.1.0, build 1). The version goes to the server in X-Rizqim-App-Version, so a drift would
# make the update notice wrong. Run from anywhere; exits 1 on any mismatch.
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
status=0

for app in cashier waiter kds customer_display; do
  pubspec="$root/pos/apps/$app/pubspec.yaml"
  config="$root/pos/apps/$app/lib/src/app_config.dart"
  if [ ! -f "$pubspec" ] || [ ! -f "$config" ]; then
    echo "FAIL $app: pubspec.yaml or lib/src/app_config.dart not found" >&2
    status=1
    continue
  fi

  bad=0
  line="$(sed -n -E 's/^version:[[:space:]]*([^[:space:]#]+).*/\1/p' "$pubspec" | head -n 1)"
  want_version="${line%%+*}"
  want_build=""
  if [[ "$line" == *+* ]]; then want_build="${line#*+}"; fi

  have_version="$(sed -n -E "s/^[[:space:]]*static const String version = '([^']*)';.*/\1/p" "$config" | head -n 1)"
  have_build="$(sed -n -E 's/^[[:space:]]*static const int build = ([0-9]+);.*/\1/p' "$config" | head -n 1)"

  if [ -z "$line" ] || [ -z "$have_version" ]; then
    echo "FAIL $app: cannot read the version (pubspec '$line', AppConfig.version '$have_version')" >&2
    status=1
    continue
  fi
  if [ "$have_version" != "$want_version" ]; then
    echo "FAIL $app: AppConfig.version is $have_version, pubspec.yaml says $want_version" >&2
    status=1
    bad=1
  fi
  if [ "$have_build" != "$want_build" ]; then
    echo "FAIL $app: AppConfig.build is '${have_build:-missing}', pubspec.yaml says '${want_build:-missing}'" >&2
    status=1
    bad=1
  fi
  [ "$bad" -eq 0 ] && echo "ok   $app $want_version+$want_build"
done

exit "$status"
