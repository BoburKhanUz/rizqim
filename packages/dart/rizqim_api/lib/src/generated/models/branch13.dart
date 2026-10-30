// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'pos_settings4.dart';

part 'branch13.g.dart';

@JsonSerializable()
class Branch13 {
  const Branch13({
    required this.id,
    required this.name,
    required this.address,
    required this.phone,
    required this.timezone,
    required this.businessDayStartsAt,
    required this.businessDate,
    required this.posSettings,
  });

  factory Branch13.fromJson(Map<String, Object?> json) =>
      _$Branch13FromJson(json);

  final int id;
  final String name;
  final String? address;
  final String? phone;
  final String timezone;
  @JsonKey(name: 'business_day_starts_at')
  final String businessDayStartsAt;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'pos_settings')
  final PosSettings4 posSettings;

  Map<String, Object?> toJson() => _$Branch13ToJson(this);
}
