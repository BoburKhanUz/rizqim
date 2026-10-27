// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data191.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data191 _$Data191FromJson(Map<String, dynamic> json) => Data191(
  totalStores: (json['total_stores'] as num).toInt(),
  activeStores: (json['active_stores'] as num).toInt(),
  paymentRequiredStores: (json['payment_required_stores'] as num).toInt(),
  totalUsers: (json['total_users'] as num).toInt(),
  totalGmv: json['total_gmv'] as String,
  totalCommission: json['total_commission'] as num,
  monthlyGmv: json['monthly_gmv'] as String,
  monthlyCommission: json['monthly_commission'] as num,
  last30DaysGmv: json['last_30_days_gmv'] as String,
  last30DaysCommission: json['last_30_days_commission'] as num,
  todayGmv: json['today_gmv'] as String,
  todayOrders: (json['today_orders'] as num).toInt(),
  todayCommission: json['today_commission'] as num,
  pendingPayouts: (json['pending_payouts'] as num).toInt(),
  totalStoreDebt: json['total_store_debt'] as num,
  totalCashbackBalance: json['total_cashback_balance'] as num,
);

Map<String, dynamic> _$Data191ToJson(Data191 instance) => <String, dynamic>{
  'total_stores': instance.totalStores,
  'active_stores': instance.activeStores,
  'payment_required_stores': instance.paymentRequiredStores,
  'total_users': instance.totalUsers,
  'total_gmv': instance.totalGmv,
  'total_commission': instance.totalCommission,
  'monthly_gmv': instance.monthlyGmv,
  'monthly_commission': instance.monthlyCommission,
  'last_30_days_gmv': instance.last30DaysGmv,
  'last_30_days_commission': instance.last30DaysCommission,
  'today_gmv': instance.todayGmv,
  'today_orders': instance.todayOrders,
  'today_commission': instance.todayCommission,
  'pending_payouts': instance.pendingPayouts,
  'total_store_debt': instance.totalStoreDebt,
  'total_cashback_balance': instance.totalCashbackBalance,
};
