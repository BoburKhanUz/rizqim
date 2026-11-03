// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'products8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Products8 _$Products8FromJson(Map<String, dynamic> json) => Products8(
  name: json['name'] as String,
  quantity: json['quantity'] as num,
  revenue: json['revenue'] as String,
  orders: (json['orders'] as num).toInt(),
);

Map<String, dynamic> _$Products8ToJson(Products8 instance) => <String, dynamic>{
  'name': instance.name,
  'quantity': instance.quantity,
  'revenue': instance.revenue,
  'orders': instance.orders,
};
