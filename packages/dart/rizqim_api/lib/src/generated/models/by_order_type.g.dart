// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_order_type.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByOrderType _$ByOrderTypeFromJson(Map<String, dynamic> json) => ByOrderType(
  type: json['type'] as String,
  orders: (json['orders'] as num).toInt(),
  revenue: json['revenue'] as num,
);

Map<String, dynamic> _$ByOrderTypeToJson(ByOrderType instance) =>
    <String, dynamic>{
      'type': instance.type,
      'orders': instance.orders,
      'revenue': instance.revenue,
    };
