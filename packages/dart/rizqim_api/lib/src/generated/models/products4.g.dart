// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'products4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Products4 _$Products4FromJson(Map<String, dynamic> json) => Products4(
  name: json['name'] as String,
  quantity: json['quantity'] as num,
  revenue: json['revenue'] as String,
  orders: (json['orders'] as num).toInt(),
);

Map<String, dynamic> _$Products4ToJson(Products4 instance) => <String, dynamic>{
  'name': instance.name,
  'quantity': instance.quantity,
  'revenue': instance.revenue,
  'orders': instance.orders,
};
