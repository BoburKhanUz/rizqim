// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'working_hours4.g.dart';

@JsonSerializable()
class WorkingHours4 {
  const WorkingHours4({
    required this.open,
    required this.close,
    required this.allDay,
    required this.overnight,
    required this.timezone,
  });

  factory WorkingHours4.fromJson(Map<String, Object?> json) =>
      _$WorkingHours4FromJson(json);

  final String? open;
  final String? close;
  @JsonKey(name: 'all_day')
  final bool allDay;
  final bool overnight;
  final String timezone;

  Map<String, Object?> toJson() => _$WorkingHours4ToJson(this);
}
