// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'shift2.dart';
import 'report.dart';
import 'shifts4.dart';

part 'data346.g.dart';

@JsonSerializable()
class Data346 {
  const Data346({
    required this.shift,
    required this.report,
    required this.shifts,
  });

  factory Data346.fromJson(Map<String, Object?> json) =>
      _$Data346FromJson(json);

  final Shift2? shift;
  final Report? report;
  final List<Shifts4> shifts;

  Map<String, Object?> toJson() => _$Data346ToJson(this);
}
