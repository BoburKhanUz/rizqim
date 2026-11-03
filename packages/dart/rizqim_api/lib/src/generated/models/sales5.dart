// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sales5.g.dart';

@JsonSerializable()
class Sales5 {
  const Sales5({
    required this.bills,
    required this.received,
    required this.refunded,
    required this.net,
    required this.billsTotal,
    required this.subtotal,
    required this.discount,
    required this.roomFee,
    required this.minSpendTopup,
    required this.serviceCharge,
    required this.placeFee,
    required this.packaging,
    required this.averageBill,
  });

  factory Sales5.fromJson(Map<String, Object?> json) => _$Sales5FromJson(json);

  final int bills;
  final String received;
  final String refunded;
  final String net;
  @JsonKey(name: 'bills_total')
  final String billsTotal;
  final String subtotal;
  final String discount;
  @JsonKey(name: 'room_fee')
  final String roomFee;
  @JsonKey(name: 'min_spend_topup')
  final String minSpendTopup;
  @JsonKey(name: 'service_charge')
  final String serviceCharge;
  @JsonKey(name: 'place_fee')
  final String placeFee;
  final String packaging;
  @JsonKey(name: 'average_bill')
  final String averageBill;

  Map<String, Object?> toJson() => _$Sales5ToJson(this);
}
