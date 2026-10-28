// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'plan4.g.dart';

@JsonSerializable()
class Plan4 {
  const Plan4({
    required this.code,
    required this.name,
    required this.tier,
    required this.version,
  });

  factory Plan4.fromJson(Map<String, Object?> json) => _$Plan4FromJson(json);

  final String code;
  final String name;
  final String? tier;
  final int version;

  Map<String, Object?> toJson() => _$Plan4ToJson(this);
}
