#!/usr/bin/env bash
# Regenerates lib/src/generated from packages/api-spec/openapi.json:
#   0. tool/prepare_spec.dart (free-form objects for the few union responses swagger_parser cannot generate);
#   1. swagger_parser (pure Dart, no Java) writes the Retrofit clients and json_serializable models;
#   2. build_runner writes the *.g.dart files;
#   3. dart format makes the output pass `dart format --set-exit-if-changed`.
# Run after every change of the spec (after `php artisan api-spec:export`). The generated code is committed.
set -euo pipefail
cd "$(dirname "$0")/.."

rm -rf lib/src/generated
dart run tool/prepare_spec.dart
dart run swagger_parser
dart run build_runner build --delete-conflicting-outputs
dart format lib/src/generated
