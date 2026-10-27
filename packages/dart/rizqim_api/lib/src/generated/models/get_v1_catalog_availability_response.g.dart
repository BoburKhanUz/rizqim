// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_catalog_availability_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1CatalogAvailabilityResponse _$GetV1CatalogAvailabilityResponseFromJson(
  Map<String, dynamic> json,
) => GetV1CatalogAvailabilityResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data6.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1CatalogAvailabilityResponseToJson(
  GetV1CatalogAvailabilityResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
