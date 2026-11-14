// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'day.g.dart';

@JsonSerializable()
class Day {
  const Day({required this.start, required this.end});

  factory Day.fromJson(Map<String, Object?> json) => _$DayFromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$DayToJson(this);
}
