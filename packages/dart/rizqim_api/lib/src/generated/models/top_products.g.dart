// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'top_products.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

TopProducts _$TopProductsFromJson(Map<String, dynamic> json) => TopProducts(
  name: json['name'] as String,
  quantity: json['quantity'] as num,
  revenue: json['revenue'] as num,
);

Map<String, dynamic> _$TopProductsToJson(TopProducts instance) =>
    <String, dynamic>{
      'name': instance.name,
      'quantity': instance.quantity,
      'revenue': instance.revenue,
    };
