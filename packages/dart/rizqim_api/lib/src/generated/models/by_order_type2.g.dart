// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_order_type2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByOrderType2 _$ByOrderType2FromJson(Map<String, dynamic> json) => ByOrderType2(
  type: json['type'] as String,
  orders: (json['orders'] as num).toInt(),
  revenue: json['revenue'] as String,
);

Map<String, dynamic> _$ByOrderType2ToJson(ByOrderType2 instance) =>
    <String, dynamic>{
      'type': instance.type,
      'orders': instance.orders,
      'revenue': instance.revenue,
    };
