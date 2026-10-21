// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_branches_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBranchesRequestBody _$V1OwnerBranchesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBranchesRequestBody(
  name: json['name'] as String,
  address: json['address'] as String,
  regionId: (json['region_id'] as num).toInt(),
  districtId: (json['district_id'] as num).toInt(),
  latitude: json['latitude'] as num,
  longitude: json['longitude'] as num,
  phone: json['phone'] as String?,
  radiusKm: json['radius_km'] as num?,
  openTime: json['open_time'] as String?,
  closeTime: json['close_time'] as String?,
  isActive: json['is_active'] as bool?,
  status: json['status'] == null
      ? null
      : Status3.fromJson(json['status'] as String),
);

Map<String, dynamic> _$V1OwnerBranchesRequestBodyToJson(
  V1OwnerBranchesRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'phone': instance.phone,
  'address': instance.address,
  'region_id': instance.regionId,
  'district_id': instance.districtId,
  'latitude': instance.latitude,
  'longitude': instance.longitude,
  'radius_km': instance.radiusKm,
  'open_time': instance.openTime,
  'close_time': instance.closeTime,
  'is_active': instance.isActive,
  'status': instance.status,
};
