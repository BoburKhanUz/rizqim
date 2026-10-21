// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'products.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Products _$ProductsFromJson(Map<String, dynamic> json) => Products(
  name: json['name'] as String,
  quantity: json['quantity'] as num,
  revenue: json['revenue'] as String,
  orders: (json['orders'] as num).toInt(),
);

Map<String, dynamic> _$ProductsToJson(Products instance) => <String, dynamic>{
  'name': instance.name,
  'quantity': instance.quantity,
  'revenue': instance.revenue,
  'orders': instance.orders,
};
