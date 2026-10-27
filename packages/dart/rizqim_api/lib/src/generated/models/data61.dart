// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'pos_settings.dart';

part 'data61.g.dart';

@JsonSerializable()
class Data61 {
  const Data61({
    required this.branchId,
    required this.timezone,
    required this.businessDayStartsAt,
    required this.posSettings,
  });

  factory Data61.fromJson(Map<String, Object?> json) => _$Data61FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String timezone;
  @JsonKey(name: 'business_day_starts_at')
  final String businessDayStartsAt;
  @JsonKey(name: 'pos_settings')
  final PosSettings posSettings;

  Map<String, Object?> toJson() => _$Data61ToJson(this);
}
