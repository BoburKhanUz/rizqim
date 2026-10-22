// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'shift.g.dart';

@JsonSerializable()
class Shift {
  const Shift({
    required this.uuid,
    required this.status,
    required this.businessDate,
    required this.openedAt,
    required this.openedBy,
    required this.openingCash,
    required this.deviceId,
  });

  factory Shift.fromJson(Map<String, Object?> json) => _$ShiftFromJson(json);

  final String uuid;
  final String status;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'opened_at')
  final String openedAt;
  @JsonKey(name: 'opened_by')
  final int? openedBy;
  @JsonKey(name: 'opening_cash')
  final String openingCash;
  @JsonKey(name: 'device_id')
  final String deviceId;

  Map<String, Object?> toJson() => _$ShiftToJson(this);
}
