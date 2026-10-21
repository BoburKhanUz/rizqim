// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'working_hours.g.dart';

@JsonSerializable()
class WorkingHours {
  const WorkingHours({
    required this.open,
    required this.close,
    required this.allDay,
    required this.overnight,
    required this.timezone,
  });

  factory WorkingHours.fromJson(Map<String, Object?> json) =>
      _$WorkingHoursFromJson(json);

  final String? open;
  final String? close;
  @JsonKey(name: 'all_day')
  final bool allDay;
  final bool overnight;
  final String timezone;

  Map<String, Object?> toJson() => _$WorkingHoursToJson(this);
}
