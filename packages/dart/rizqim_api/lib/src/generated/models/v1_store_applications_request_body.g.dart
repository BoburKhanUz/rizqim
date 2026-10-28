// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_store_applications_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1StoreApplicationsRequestBody _$V1StoreApplicationsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1StoreApplicationsRequestBody(
  name: json['name'] as String,
  format: StoreFormat.fromJson(json['format'] as String),
  ownerName: json['owner_name'] as String,
  phone: json['phone'] as String,
  regionId: (json['region_id'] as num?)?.toInt(),
  city: json['city'] as String?,
  tin: json['tin'] as String?,
  comment: json['comment'] as String?,
  source: json['source'] == null
      ? null
      : Source3.fromJson(json['source'] as String?),
  website: json['website'] as String?,
);

Map<String, dynamic> _$V1StoreApplicationsRequestBodyToJson(
  V1StoreApplicationsRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'format': instance.format,
  'region_id': instance.regionId,
  'city': instance.city,
  'owner_name': instance.ownerName,
  'phone': instance.phone,
  'tin': instance.tin,
  'comment': instance.comment,
  'source': instance.source,
  'website': instance.website,
};
