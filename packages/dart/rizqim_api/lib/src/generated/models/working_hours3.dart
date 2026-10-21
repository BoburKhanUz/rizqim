// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'working_hours3.g.dart';

@JsonSerializable()
class WorkingHours3 {
  const WorkingHours3({
    required this.open,
    required this.close,
    required this.allDay,
    required this.overnight,
    required this.timezone,
  });

  factory WorkingHours3.fromJson(Map<String, Object?> json) =>
      _$WorkingHours3FromJson(json);

  final String? open;
  final String? close;
  @JsonKey(name: 'all_day')
  final bool allDay;
  final bool overnight;
  final String timezone;

  Map<String, Object?> toJson() => _$WorkingHours3ToJson(this);
}
