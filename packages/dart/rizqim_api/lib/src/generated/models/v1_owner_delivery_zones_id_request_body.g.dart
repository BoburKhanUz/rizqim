// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_delivery_zones_id_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerDeliveryZonesIdRequestBody _$V1OwnerDeliveryZonesIdRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerDeliveryZonesIdRequestBody(
  name: json['name'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  priority: (json['priority'] as num?)?.toInt(),
  active: json['active'] as bool?,
  deliveryPrice: json['delivery_price'] as num?,
  minOrderAmount: json['min_order_amount'] as num?,
  freeDeliveryFrom: json['free_delivery_from'] as num?,
  etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
  polygon: json['polygon'],
);

Map<String, dynamic> _$V1OwnerDeliveryZonesIdRequestBodyToJson(
  V1OwnerDeliveryZonesIdRequestBody instance,
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
