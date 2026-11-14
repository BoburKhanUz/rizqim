// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'schedule_template2.g.dart';

@JsonSerializable()
class ScheduleTemplate2 {
  const ScheduleTemplate2({
    required this.kind,
    required this.percent,
    required this.daysBefore,
  });

  factory ScheduleTemplate2.fromJson(Map<String, Object?> json) =>
      _$ScheduleTemplate2FromJson(json);

  final String kind;
  final String? percent;
  @JsonKey(name: 'days_before')
  final int? daysBefore;

  Map<String, Object?> toJson() => _$ScheduleTemplate2ToJson(this);
}
