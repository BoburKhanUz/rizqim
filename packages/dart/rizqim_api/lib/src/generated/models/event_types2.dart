// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'event_types2.g.dart';

@JsonSerializable()
class EventTypes2 {
  const EventTypes2({required this.value, required this.label});

  factory EventTypes2.fromJson(Map<String, Object?> json) =>
      _$EventTypes2FromJson(json);

  final String value;
  final String label;

  Map<String, Object?> toJson() => _$EventTypes2ToJson(this);
}
