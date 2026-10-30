// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'days2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Days2 _$Days2FromJson(Map<String, dynamic> json) => Days2(
  businessDate: json['business_date'] as String,
  startedAt: json['started_at'] as String,
  endsAt: json['ends_at'] as String,
  orders: (json['orders'] as num).toInt(),
  posOrders: (json['pos_orders'] as num).toInt(),
  onlineOrders: (json['online_orders'] as num).toInt(),
  revenue: json['revenue'] as String,
  packagingAmount: json['packaging_amount'] as String,
  afterMidnightOrders: (json['after_midnight_orders'] as num).toInt(),
  firstOrderAt: json['first_order_at'] as String?,
  lastOrderAt: json['last_order_at'] as String?,
  shifts: (json['shifts'] as List<dynamic>)
      .map((e) => Shifts.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Days2ToJson(Days2 instance) => <String, dynamic>{
  'business_date': instance.businessDate,
  'started_at': instance.startedAt,
  'ends_at': instance.endsAt,
  'orders': instance.orders,
  'pos_orders': instance.posOrders,
  'online_orders': instance.onlineOrders,
  'revenue': instance.revenue,
  'packaging_amount': instance.packagingAmount,
  'after_midnight_orders': instance.afterMidnightOrders,
  'first_order_at': instance.firstOrderAt,
  'last_order_at': instance.lastOrderAt,
  'shifts': instance.shifts,
};
