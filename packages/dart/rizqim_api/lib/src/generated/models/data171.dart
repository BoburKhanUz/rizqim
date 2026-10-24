// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'shift2.dart';
import 'report.dart';
import 'shifts.dart';

part 'data171.g.dart';

@JsonSerializable()
class Data171 {
  const Data171({
    required this.shift,
    required this.report,
    required this.shifts,
  });

  factory Data171.fromJson(Map<String, Object?> json) =>
      _$Data171FromJson(json);

  final Shift2? shift;
  final Report? report;
  final List<Shifts> shifts;

  Map<String, Object?> toJson() => _$Data171ToJson(this);
}
