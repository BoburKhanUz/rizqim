// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_devices_pair_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1DevicesPairResponse _$PostV1DevicesPairResponseFromJson(
  Map<String, dynamic> json,
) => PostV1DevicesPairResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data10.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1DevicesPairResponseToJson(
  PostV1DevicesPairResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
