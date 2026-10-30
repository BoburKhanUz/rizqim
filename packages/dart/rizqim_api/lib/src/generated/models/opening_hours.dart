// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'opening_hours.g.dart';

@JsonSerializable()
class OpeningHours {
  const OpeningHours({required this.open, required this.close});

  factory OpeningHours.fromJson(Map<String, Object?> json) =>
      _$OpeningHoursFromJson(json);

  final String? open;
  final String? close;

  Map<String, Object?> toJson() => _$OpeningHoursToJson(this);
}
