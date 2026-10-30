// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'periods.g.dart';

@JsonSerializable()
class Periods {
  const Periods({required this.value, required this.label});

  factory Periods.fromJson(Map<String, Object?> json) =>
      _$PeriodsFromJson(json);

  final String value;
  final String label;

  Map<String, Object?> toJson() => _$PeriodsToJson(this);
}
