// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'schedule_template.g.dart';

@JsonSerializable()
class ScheduleTemplate {
  const ScheduleTemplate({
    required this.kind,
    required this.percent,
    required this.daysBefore,
  });

  factory ScheduleTemplate.fromJson(Map<String, Object?> json) =>
      _$ScheduleTemplateFromJson(json);

  final String kind;
  final String? percent;
  @JsonKey(name: 'days_before')
  final int? daysBefore;

  Map<String, Object?> toJson() => _$ScheduleTemplateToJson(this);
}
