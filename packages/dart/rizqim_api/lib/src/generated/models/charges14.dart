// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'waivers9.dart';

part 'charges14.g.dart';

@JsonSerializable()
class Charges14 {
  const Charges14({
    required this.itemsSubtotal,
    required this.discount,
    required this.serviceChargePercent,
    required this.serviceChargeAmount,
    required this.placeFeeAmount,
    required this.roomFeeAmount,
    required this.roomTariffId,
    required this.businessDate,
    required this.dayType,
    required this.minSpend,
    required this.minSpendTopupAmount,
    required this.waived,
    required this.total,
    required this.waivers,
  });

  factory Charges14.fromJson(Map<String, Object?> json) =>
      _$Charges14FromJson(json);

  @JsonKey(name: 'items_subtotal')
  final String itemsSubtotal;
  final String discount;
  @JsonKey(name: 'service_charge_percent')
  final String serviceChargePercent;
  @JsonKey(name: 'service_charge_amount')
  final String serviceChargeAmount;
  @JsonKey(name: 'place_fee_amount')
  final String placeFeeAmount;
  @JsonKey(name: 'room_fee_amount')
  final String roomFeeAmount;
  @JsonKey(name: 'room_tariff_id')
  final int? roomTariffId;
  @JsonKey(name: 'business_date')
  final String? businessDate;
  @JsonKey(name: 'day_type')
  final String? dayType;
  @JsonKey(name: 'min_spend')
  final String minSpend;
  @JsonKey(name: 'min_spend_topup_amount')
  final String minSpendTopupAmount;
  final List<String> waived;
  final String total;
  final List<Waivers9> waivers;

  Map<String, Object?> toJson() => _$Charges14ToJson(this);
}
