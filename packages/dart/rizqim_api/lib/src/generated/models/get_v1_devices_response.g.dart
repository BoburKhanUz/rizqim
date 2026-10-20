// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_devices_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1DevicesResponse _$GetV1DevicesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1DevicesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: (json['data'] as List<dynamic>)
      .map((e) => Data8.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$GetV1DevicesResponseToJson(
  GetV1DevicesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
