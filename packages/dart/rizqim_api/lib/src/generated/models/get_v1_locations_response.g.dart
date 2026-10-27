// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_locations_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1LocationsResponse _$GetV1LocationsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1LocationsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data18.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1LocationsResponseToJson(
  GetV1LocationsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
