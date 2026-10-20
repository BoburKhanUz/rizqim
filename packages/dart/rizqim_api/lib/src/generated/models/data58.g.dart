// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data58.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data58 _$Data58FromJson(Map<String, dynamic> json) => Data58(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  polygon: Polygon.fromJson(json['polygon'] as Map<String, dynamic>),
  deliveryPrice: json['delivery_price'] as String,
  minOrderAmount: json['min_order_amount'] as String,
  freeDeliveryFrom: json['free_delivery_from'] as String?,
  etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
  priority: (json['priority'] as num).toInt(),
);

Map<String, dynamic> _$Data58ToJson(Data58 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'polygon': instance.polygon,
  'delivery_price': instance.deliveryPrice,
  'min_order_amount': instance.minOrderAmount,
  'free_delivery_from': instance.freeDeliveryFrom,
  'eta_minutes': instance.etaMinutes,
  'priority': instance.priority,
};
