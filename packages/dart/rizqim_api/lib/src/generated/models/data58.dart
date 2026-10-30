// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'pos_settings2.dart';

part 'data58.g.dart';

@JsonSerializable()
class Data58 {
  const Data58({
    required this.branchId,
    required this.timezone,
    required this.businessDayStartsAt,
    required this.posSettings,
  });

  factory Data58.fromJson(Map<String, Object?> json) => _$Data58FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String timezone;
  @JsonKey(name: 'business_day_starts_at')
  final String businessDayStartsAt;
  @JsonKey(name: 'pos_settings')
  final PosSettings2 posSettings;

  Map<String, Object?> toJson() => _$Data58ToJson(this);
}
