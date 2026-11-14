// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kind3.dart';

part 'schedule_template3.g.dart';

@JsonSerializable()
class ScheduleTemplate3 {
  const ScheduleTemplate3({required this.kind, this.percent, this.daysBefore});

  factory ScheduleTemplate3.fromJson(Map<String, Object?> json) =>
      _$ScheduleTemplate3FromJson(json);

  final Kind3 kind;
  final num? percent;
  @JsonKey(name: 'days_before')
  final int? daysBefore;

  Map<String, Object?> toJson() => _$ScheduleTemplate3ToJson(this);
}
