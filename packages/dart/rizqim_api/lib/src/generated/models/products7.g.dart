// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'products7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Products7 _$Products7FromJson(Map<String, dynamic> json) => Products7(
  name: json['name'] as String,
  quantity: json['quantity'] as num,
  revenue: json['revenue'] as String,
  orders: (json['orders'] as num).toInt(),
);

Map<String, dynamic> _$Products7ToJson(Products7 instance) => <String, dynamic>{
  'name': instance.name,
  'quantity': instance.quantity,
  'revenue': instance.revenue,
  'orders': instance.orders,
};
