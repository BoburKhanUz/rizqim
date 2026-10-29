// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'open_shifts7.g.dart';

@JsonSerializable()
class OpenShifts7 {
  const OpenShifts7({
    required this.uuid,
    required this.deviceName,
    required this.businessDate,
    required this.openedAt,
    required this.expectedCash,
  });

  factory OpenShifts7.fromJson(Map<String, Object?> json) =>
      _$OpenShifts7FromJson(json);

  final String uuid;
  @JsonKey(name: 'device_name')
  final String? deviceName;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'opened_at')
  final String openedAt;
  @JsonKey(name: 'expected_cash')
  final String expectedCash;

  Map<String, Object?> toJson() => _$OpenShifts7ToJson(this);
}
