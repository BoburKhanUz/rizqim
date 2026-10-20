// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'address.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Address _$AddressFromJson(Map<String, dynamic> json) => Address(
  id: (json['id'] as num).toInt(),
  userId: (json['user_id'] as num).toInt(),
  title: json['title'] as String,
  address: json['address'] as String,
  latitude: json['latitude'] as String?,
  longitude: json['longitude'] as String?,
  apartment: json['apartment'] as String?,
  entrance: json['entrance'] as String?,
  floor: json['floor'] as String?,
  intercom: json['intercom'] as String?,
  comment: json['comment'] as String?,
  isDefault: json['is_default'] as bool,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  regionId: (json['region_id'] as num?)?.toInt(),
  districtId: (json['district_id'] as num?)?.toInt(),
  user: json['user'] == null
      ? null
      : User.fromJson(json['user'] as Map<String, dynamic>),
  region: json['region'] == null
      ? null
      : Region.fromJson(json['region'] as Map<String, dynamic>),
  district: json['district'] == null
      ? null
      : District.fromJson(json['district'] as Map<String, dynamic>),
);

Map<String, dynamic> _$AddressToJson(Address instance) => <String, dynamic>{
  'id': instance.id,
  'user_id': instance.userId,
  'title': instance.title,
  'address': instance.address,
  'latitude': instance.latitude,
  'longitude': instance.longitude,
  'apartment': instance.apartment,
  'entrance': instance.entrance,
  'floor': instance.floor,
  'intercom': instance.intercom,
  'comment': instance.comment,
  'is_default': instance.isDefault,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'region_id': instance.regionId,
  'district_id': instance.districtId,
  'user': instance.user,
  'region': instance.region,
  'district': instance.district,
};
