// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'totals.g.dart';

@JsonSerializable()
class Totals {
  const Totals({
    required this.operations,
    required this.applied,
    required this.conflict,
    required this.rejected,
    required this.duplicate,
    required this.skipped,
    required this.pending,
    required this.renumbered,
  });

  factory Totals.fromJson(Map<String, Object?> json) => _$TotalsFromJson(json);

  final int operations;
  final int applied;
  final int conflict;
  final int rejected;
  final int duplicate;
  final int skipped;
  final int pending;
  final int renumbered;

  Map<String, Object?> toJson() => _$TotalsToJson(this);
}
