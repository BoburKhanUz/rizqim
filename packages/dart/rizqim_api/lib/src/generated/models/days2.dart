// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'shifts.dart';

part 'days2.g.dart';

@JsonSerializable()
class Days2 {
  const Days2({
    required this.businessDate,
    required this.startedAt,
    required this.endsAt,
    required this.orders,
    required this.posOrders,
    required this.onlineOrders,
    required this.revenue,
    required this.packagingAmount,
    required this.afterMidnightOrders,
    required this.firstOrderAt,
    required this.lastOrderAt,
    required this.shifts,
  });

  factory Days2.fromJson(Map<String, Object?> json) => _$Days2FromJson(json);

  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'started_at')
  final String startedAt;
  @JsonKey(name: 'ends_at')
  final String endsAt;
  final int orders;
  @JsonKey(name: 'pos_orders')
  final int posOrders;
  @JsonKey(name: 'online_orders')
  final int onlineOrders;
  final String revenue;
  @JsonKey(name: 'packaging_amount')
  final String packagingAmount;
  @JsonKey(name: 'after_midnight_orders')
  final int afterMidnightOrders;
  @JsonKey(name: 'first_order_at')
  final String? firstOrderAt;
  @JsonKey(name: 'last_order_at')
  final String? lastOrderAt;
  final List<Shifts> shifts;

  Map<String, Object?> toJson() => _$Days2ToJson(this);
}
