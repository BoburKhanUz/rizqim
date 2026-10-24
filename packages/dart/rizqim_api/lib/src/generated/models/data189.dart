// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data189.g.dart';

@JsonSerializable()
class Data189 {
  const Data189({
    required this.totalStores,
    required this.activeStores,
    required this.paymentRequiredStores,
    required this.totalUsers,
    required this.totalGmv,
    required this.totalCommission,
    required this.monthlyGmv,
    required this.monthlyCommission,
    required this.last30DaysGmv,
    required this.last30DaysCommission,
    required this.todayGmv,
    required this.todayOrders,
    required this.todayCommission,
    required this.pendingPayouts,
    required this.totalStoreDebt,
    required this.totalCashbackBalance,
  });

  factory Data189.fromJson(Map<String, Object?> json) =>
      _$Data189FromJson(json);

  @JsonKey(name: 'total_stores')
  final int totalStores;
  @JsonKey(name: 'active_stores')
  final int activeStores;
  @JsonKey(name: 'payment_required_stores')
  final int paymentRequiredStores;
  @JsonKey(name: 'total_users')
  final int totalUsers;
  @JsonKey(name: 'total_gmv')
  final String totalGmv;
  @JsonKey(name: 'total_commission')
  final num totalCommission;
  @JsonKey(name: 'monthly_gmv')
  final String monthlyGmv;
  @JsonKey(name: 'monthly_commission')
  final num monthlyCommission;
  @JsonKey(name: 'last_30_days_gmv')
  final String last30DaysGmv;
  @JsonKey(name: 'last_30_days_commission')
  final num last30DaysCommission;
  @JsonKey(name: 'today_gmv')
  final String todayGmv;
  @JsonKey(name: 'today_orders')
  final int todayOrders;
  @JsonKey(name: 'today_commission')
  final num todayCommission;
  @JsonKey(name: 'pending_payouts')
  final int pendingPayouts;
  @JsonKey(name: 'total_store_debt')
  final num totalStoreDebt;
  @JsonKey(name: 'total_cashback_balance')
  final num totalCashbackBalance;

  Map<String, Object?> toJson() => _$Data189ToJson(this);
}
