// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_addresses_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AddressesRequestBody _$V1AddressesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AddressesRequestBody(
  title: json['title'] as String,
  address: json['address'] as String,
  latitude: json['latitude'] as num?,
  longitude: json['longitude'] as num?,
  apartment: json['apartment'] as String?,
  entrance: json['entrance'] as String?,
  floor: json['floor'] as String?,
  intercom: json['intercom'] as String?,
  comment: json['comment'] as String?,
  isDefault: json['is_default'] as bool?,
  regionId: (json['region_id'] as num?)?.toInt(),
  districtId: (json['district_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1AddressesRequestBodyToJson(
  V1AddressesRequestBody instance,
) => <String, dynamic>{
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
  'region_id': instance.regionId,
  'district_id': instance.districtId,
};
