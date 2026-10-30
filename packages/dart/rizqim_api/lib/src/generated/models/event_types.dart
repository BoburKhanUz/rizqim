// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'event_types.g.dart';

@JsonSerializable()
class EventTypes {
  const EventTypes({required this.value, required this.label});

  factory EventTypes.fromJson(Map<String, Object?> json) =>
      _$EventTypesFromJson(json);

  final String value;
  final String label;

  Map<String, Object?> toJson() => _$EventTypesToJson(this);
}
