// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'member.g.dart';

@JsonSerializable()
class Member {
  const Member({
    required this.uuid,
    required this.name,
    required this.phone,
    required this.code,
    required this.dailyLimit,
    required this.mealsPerDay,
    required this.isActive,
  });

  factory Member.fromJson(Map<String, Object?> json) => _$MemberFromJson(json);

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

  Map<String, Object?> toJson() => _$MemberToJson(this);
}
