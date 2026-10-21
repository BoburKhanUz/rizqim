// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_delivery_zones_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerDeliveryZonesRequestBody _$V1OwnerDeliveryZonesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerDeliveryZonesRequestBody(
  name: json['name'] as String,
  polygon: json['polygon'],
  branchId: (json['branch_id'] as num?)?.toInt(),
  priority: (json['priority'] as num?)?.toInt(),
  active: json['active'] as bool?,
  deliveryPrice: json['delivery_price'] as num?,
  minOrderAmount: json['min_order_amount'] as num?,
  freeDeliveryFrom: json['free_delivery_from'] as num?,
  etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerDeliveryZonesRequestBodyToJson(
  V1OwnerDeliveryZonesRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'branch_id': instance.branchId,
  'priority': instance.priority,
  'active': instance.active,
  'delivery_price': instance.deliveryPrice,
  'min_order_amount': instance.minOrderAmount,
  'free_delivery_from': instance.freeDeliveryFrom,
  'eta_minutes': instance.etaMinutes,
  'polygon': instance.polygon,
};
