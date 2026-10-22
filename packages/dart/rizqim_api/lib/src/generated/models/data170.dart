// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'shift2.dart';
import 'report.dart';
import 'shifts.dart';

part 'data170.g.dart';

@JsonSerializable()
class Data170 {
  const Data170({
    required this.shift,
    required this.report,
    required this.shifts,
  });

  factory Data170.fromJson(Map<String, Object?> json) =>
      _$Data170FromJson(json);

  final Shift2? shift;
  final Report? report;
  final List<Shifts> shifts;

  Map<String, Object?> toJson() => _$Data170ToJson(this);
}
