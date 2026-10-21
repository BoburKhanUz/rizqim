// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data87.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data87 _$Data87FromJson(Map<String, dynamic> json) => Data87(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  polygon: Polygon5.fromJson(json['polygon'] as Map<String, dynamic>),
  deliveryPrice: json['delivery_price'] as String,
  minOrderAmount: json['min_order_amount'] as String,
  freeDeliveryFrom: json['free_delivery_from'] as String?,
  etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
  priority: (json['priority'] as num).toInt(),
);

Map<String, dynamic> _$Data87ToJson(Data87 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'polygon': instance.polygon,
  'delivery_price': instance.deliveryPrice,
  'min_order_amount': instance.minOrderAmount,
  'free_delivery_from': instance.freeDeliveryFrom,
  'eta_minutes': instance.etaMinutes,
  'priority': instance.priority,
};
