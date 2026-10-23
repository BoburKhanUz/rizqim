// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_waiter5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByWaiter5 _$ByWaiter5FromJson(Map<String, dynamic> json) => ByWaiter5(
  waiterId: (json['waiter_id'] as num?)?.toInt(),
  name: json['name'] as String?,
  orders: (json['orders'] as num).toInt(),
  bills: (json['bills'] as num).toInt(),
  amount: json['amount'] as String,
  serviceCharge: json['service_charge'] as String,
);

Map<String, dynamic> _$ByWaiter5ToJson(ByWaiter5 instance) => <String, dynamic>{
  'waiter_id': instance.waiterId,
  'name': instance.name,
  'orders': instance.orders,
  'bills': instance.bills,
  'amount': instance.amount,
  'service_charge': instance.serviceCharge,
};
