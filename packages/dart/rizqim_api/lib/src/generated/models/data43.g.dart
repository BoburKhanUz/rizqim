// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data43.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data43 _$Data43FromJson(Map<String, dynamic> json) => Data43(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
  branch: json['branch'] == null
      ? null
      : Branch3.fromJson(json['branch'] as Map<String, dynamic>),
  polygon: Polygon.fromJson(json['polygon'] as Map<String, dynamic>),
  bbox: Bbox.fromJson(json['bbox'] as Map<String, dynamic>),
  areaKm2: json['area_km2'] as String,
  deliveryPrice: json['delivery_price'] as String,
  minOrderAmount: json['min_order_amount'] as String,
  freeDeliveryFrom: json['free_delivery_from'] as String?,
  etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
  priority: (json['priority'] as num).toInt(),
  active: json['active'] as bool,
  createdAt: json['created_at'] as String?,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$Data43ToJson(Data43 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'branch_id': instance.branchId,
  'branch': instance.branch,
  'polygon': instance.polygon,
  'bbox': instance.bbox,
  'area_km2': instance.areaKm2,
  'delivery_price': instance.deliveryPrice,
  'min_order_amount': instance.minOrderAmount,
  'free_delivery_from': instance.freeDeliveryFrom,
  'eta_minutes': instance.etaMinutes,
  'priority': instance.priority,
  'active': instance.active,
  'created_at': instance.createdAt,
  'updated_at': instance.updatedAt,
};
