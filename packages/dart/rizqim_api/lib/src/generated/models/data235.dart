// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data235.g.dart';

@JsonSerializable()
class Data235 {
  const Data235({
    required this.uuid,
    required this.name,
    required this.phone,
    required this.code,
    required this.dailyLimit,
    required this.mealsPerDay,
    required this.isActive,
    required this.note,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Data235.fromJson(Map<String, Object?> json) =>
      _$Data235FromJson(json);

  final String uuid;
  final String name;
  final String? phone;
  final String code;
  @JsonKey(name: 'daily_limit')
  final String? dailyLimit;
  @JsonKey(name: 'meals_per_day')
  final int? mealsPerDay;
  @JsonKey(name: 'is_active')
  final bool isActive;
  final String? note;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data235ToJson(this);
}
