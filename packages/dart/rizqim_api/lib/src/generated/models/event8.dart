// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'event8.g.dart';

@JsonSerializable()
class Event8 {
  const Event8({
    required this.uuid,
    required this.title,
    required this.eventType,
    required this.eventTypeLabel,
    required this.eventDate,
    required this.slot,
    required this.startsAt,
    required this.endsAt,
    required this.localTime,
    required this.status,
    required this.halls,
  });

  factory Event8.fromJson(Map<String, Object?> json) => _$Event8FromJson(json);

  final String uuid;
  final String? title;
  @JsonKey(name: 'event_type')
  final String eventType;
  @JsonKey(name: 'event_type_label')
  final String eventTypeLabel;
  @JsonKey(name: 'event_date')
  final String eventDate;
  final String slot;
  @JsonKey(name: 'starts_at')
  final String? startsAt;
  @JsonKey(name: 'ends_at')
  final String? endsAt;
  @JsonKey(name: 'local_time')
  final String localTime;
  final String status;
  final List<String> halls;

  Map<String, Object?> toJson() => _$Event8ToJson(this);
}
