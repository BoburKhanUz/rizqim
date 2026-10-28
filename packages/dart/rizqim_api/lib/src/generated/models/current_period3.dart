// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'current_period3.g.dart';

@JsonSerializable()
class CurrentPeriod3 {
  const CurrentPeriod3({required this.start, required this.end});

  factory CurrentPeriod3.fromJson(Map<String, Object?> json) =>
      _$CurrentPeriod3FromJson(json);

  final String start;
  final String end;

  Map<String, Object?> toJson() => _$CurrentPeriod3ToJson(this);
}
