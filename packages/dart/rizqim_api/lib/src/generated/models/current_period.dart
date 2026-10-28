// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'current_period.g.dart';

@JsonSerializable()
class CurrentPeriod {
  const CurrentPeriod({required this.start, required this.end});

  factory CurrentPeriod.fromJson(Map<String, Object?> json) =>
      _$CurrentPeriodFromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$CurrentPeriodToJson(this);
}
