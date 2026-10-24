// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'shift2.dart';
import 'report.dart';
import 'shifts.dart';

part 'data173.g.dart';

@JsonSerializable()
class Data173 {
  const Data173({
    required this.shift,
    required this.report,
    required this.shifts,
  });

  factory Data173.fromJson(Map<String, Object?> json) =>
      _$Data173FromJson(json);

  final Shift2? shift;
  final Report? report;
  final List<Shifts> shifts;

  Map<String, Object?> toJson() => _$Data173ToJson(this);
}
