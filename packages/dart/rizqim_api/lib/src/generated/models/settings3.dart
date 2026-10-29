// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'service_charge_rules.dart';

part 'settings3.g.dart';

@JsonSerializable()
class Settings3 {
  const Settings3({
    required this.branchId,
    required this.hallId,
    required this.serviceChargePercent,
    required this.serviceChargeChannels,
    required this.placeFeeAmount,
    required this.placeFeeMode,
    required this.placeFeeChannels,
    required this.serviceChargeRules,
    required this.serviceChargeNotice,
  });

  factory Settings3.fromJson(Map<String, Object?> json) =>
      _$Settings3FromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;

  /// P3-12: a hall's own row (null: the branch's or the store's)
  @JsonKey(name: 'hall_id')
  final int? hallId;
  @JsonKey(name: 'service_charge_percent')
  final String serviceChargePercent;
  @JsonKey(name: 'service_charge_channels')
  final List<String>? serviceChargeChannels;
  @JsonKey(name: 'place_fee_amount')
  final String placeFeeAmount;
  @JsonKey(name: 'place_fee_mode')
  final String placeFeeMode;
  @JsonKey(name: 'place_fee_channels')
  final List<String>? placeFeeChannels;

  /// P6-22: the row's service charge variants and notice (PUT /owner/pos/service-charge-rules)
  @JsonKey(name: 'service_charge_rules')
  final ServiceChargeRules serviceChargeRules;
  @JsonKey(name: 'service_charge_notice')
  final String? serviceChargeNotice;

  Map<String, Object?> toJson() => _$Settings3ToJson(this);
}
