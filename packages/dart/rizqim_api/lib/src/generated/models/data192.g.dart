// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data192.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data192 _$Data192FromJson(Map<String, dynamic> json) => Data192(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  polygon: Polygon5.fromJson(json['polygon'] as Map<String, dynamic>),
  deliveryPrice: json['delivery_price'] as String,
  minOrderAmount: json['min_order_amount'] as String,
  freeDeliveryFrom: json['free_delivery_from'] as String?,
  etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
  priority: (json['priority'] as num).toInt(),
);

Map<String, dynamic> _$Data192ToJson(Data192 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'polygon': instance.polygon,
  'delivery_price': instance.deliveryPrice,
  'min_order_amount': instance.minOrderAmount,
  'free_delivery_from': instance.freeDeliveryFrom,
  'eta_minutes': instance.etaMinutes,
  'priority': instance.priority,
};
