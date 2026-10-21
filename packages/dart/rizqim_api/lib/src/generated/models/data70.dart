// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data70.g.dart';

@JsonSerializable()
class Data70 {
  const Data70({
    required this.branchId,
    required this.serviceChargePercent,
    required this.serviceChargeChannels,
    required this.placeFeeAmount,
    required this.placeFeeMode,
    required this.placeFeeChannels,
  });

  factory Data70.fromJson(Map<String, Object?> json) => _$Data70FromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
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

  Map<String, Object?> toJson() => _$Data70ToJson(this);
}
