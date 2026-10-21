// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_geocode_reverse_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1GeocodeReverseResponse _$GetV1GeocodeReverseResponseFromJson(
  Map<String, dynamic> json,
) => GetV1GeocodeReverseResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data16.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1GeocodeReverseResponseToJson(
  GetV1GeocodeReverseResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
