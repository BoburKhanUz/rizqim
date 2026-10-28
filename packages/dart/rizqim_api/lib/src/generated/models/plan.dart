// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'plan.g.dart';

@JsonSerializable()
class Plan {
  const Plan({
    required this.code,
    required this.name,
    required this.tier,
    required this.version,
  });

  factory Plan.fromJson(Map<String, Object?> json) => _$PlanFromJson(json);

  final String code;
  final String name;
  final String? tier;
  final int version;

  Map<String, Object?> toJson() => _$PlanToJson(this);
}
