// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delivery_zone.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeliveryZone _$DeliveryZoneFromJson(Map<String, dynamic> json) => DeliveryZone(
  id: (json['id'] as num).toInt(),
  storeId: (json['store_id'] as num).toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  name: json['name'] as String,
  polygon: Polygon2.fromJson(json['polygon'] as Map<String, dynamic>),
  minLat: json['min_lat'] as String,
  maxLat: json['max_lat'] as String,
  minLng: json['min_lng'] as String,
  maxLng: json['max_lng'] as String,
  areaKm2: json['area_km2'] as String,
  deliveryPrice: json['delivery_price'] as String,
  minOrderAmount: json['min_order_amount'] as String,
  freeDeliveryFrom: json['free_delivery_from'] as String?,
  etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
  priority: (json['priority'] as num).toInt(),
  active: json['active'] as bool,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  branch: json['branch'] == null
      ? null
      : Branch.fromJson(json['branch'] as Map<String, dynamic>),
);

Map<String, dynamic> _$DeliveryZoneToJson(DeliveryZone instance) =>
    <String, dynamic>{
      'id': instance.id,
      'store_id': instance.storeId,
      'branch_id': instance.branchId,
      'name': instance.name,
      'polygon': instance.polygon,
      'min_lat': instance.minLat,
      'max_lat': instance.maxLat,
      'min_lng': instance.minLng,
      'max_lng': instance.maxLng,
      'area_km2': instance.areaKm2,
      'delivery_price': instance.deliveryPrice,
      'min_order_amount': instance.minOrderAmount,
      'free_delivery_from': instance.freeDeliveryFrom,
      'eta_minutes': instance.etaMinutes,
      'priority': instance.priority,
      'active': instance.active,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'store': instance.store,
      'branch': instance.branch,
    };
