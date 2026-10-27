// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'pos_settings2.dart';

part 'data62.g.dart';

@JsonSerializable()
class Data62 {
  const Data62({
    required this.branchId,
    required this.timezone,
    required this.businessDayStartsAt,
    required this.posSettings,
  });

  factory Data62.fromJson(Map<String, Object?> json) => _$Data62FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String timezone;
  @JsonKey(name: 'business_day_starts_at')
  final String businessDayStartsAt;
  @JsonKey(name: 'pos_settings')
  final PosSettings2 posSettings;

  Map<String, Object?> toJson() => _$Data62ToJson(this);
}
