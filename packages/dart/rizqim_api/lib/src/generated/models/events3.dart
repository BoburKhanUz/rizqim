// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'events3.g.dart';

@JsonSerializable()
class Events3 {
  const Events3({
    required this.uuid,
    required this.title,
    required this.eventType,
    required this.eventDate,
    required this.slot,
    required this.status,
    required this.plannedGuests,
  });

  factory Events3.fromJson(Map<String, Object?> json) =>
      _$Events3FromJson(json);

  final String uuid;
  final String? title;
  @JsonKey(name: 'event_type')
  final String eventType;
  @JsonKey(name: 'event_date')
  final String eventDate;
  final String slot;
  final String status;
  @JsonKey(name: 'planned_guests')
  final int plannedGuests;

  Map<String, Object?> toJson() => _$Events3ToJson(this);
}
