// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data105.g.dart';

@JsonSerializable()
class Data105 {
  const Data105({
    required this.branchId,
    required this.hallId,
    required this.serviceChargePercent,
    required this.serviceChargeChannels,
    required this.placeFeeAmount,
    required this.placeFeeMode,
    required this.placeFeeChannels,
  });

  factory Data105.fromJson(Map<String, Object?> json) =>
      _$Data105FromJson(json);

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

  Map<String, Object?> toJson() => _$Data105ToJson(this);
}
