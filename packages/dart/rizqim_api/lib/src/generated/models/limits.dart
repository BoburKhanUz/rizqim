// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'limits.g.dart';

@JsonSerializable()
class Limits {
  const Limits({required this.min, required this.max});

  factory Limits.fromJson(Map<String, Object?> json) => _$LimitsFromJson(json);

  final int min;
  final int max;

  Map<String, Object?> toJson() => _$LimitsToJson(this);
}
