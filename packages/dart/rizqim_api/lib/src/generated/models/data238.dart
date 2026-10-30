// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'shift2.dart';
import 'report.dart';
import 'shifts3.dart';

part 'data238.g.dart';

@JsonSerializable()
class Data238 {
  const Data238({
    required this.shift,
    required this.report,
    required this.shifts,
  });

  factory Data238.fromJson(Map<String, Object?> json) =>
      _$Data238FromJson(json);

  final Shift2? shift;
  final Report? report;
  final List<Shifts3> shifts;

  Map<String, Object?> toJson() => _$Data238ToJson(this);
}
