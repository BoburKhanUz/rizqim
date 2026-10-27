// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data58.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data58 _$Data58FromJson(Map<String, dynamic> json) => Data58(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  phone: json['phone'] as String?,
  address: json['address'] as String?,
  regionId: (json['region_id'] as num?)?.toInt(),
  districtId: (json['district_id'] as num?)?.toInt(),
  latitude: json['latitude'] as num?,
  longitude: json['longitude'] as num?,
  radiusKm: json['radius_km'] as num,
  status: json['status'] as String,
  isActive: json['is_active'] as bool,
  workingHours: WorkingHours2.fromJson(
    json['working_hours'] as Map<String, dynamic>,
  ),
  isOpenNow: json['is_open_now'] as bool,
  createdAt: json['created_at'] as String?,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$Data58ToJson(Data58 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'phone': instance.phone,
  'address': instance.address,
  'region_id': instance.regionId,
  'district_id': instance.districtId,
  'latitude': instance.latitude,
  'longitude': instance.longitude,
  'radius_km': instance.radiusKm,
  'status': instance.status,
  'is_active': instance.isActive,
  'working_hours': instance.workingHours,
  'is_open_now': instance.isOpenNow,
  'created_at': instance.createdAt,
  'updated_at': instance.updatedAt,
};
