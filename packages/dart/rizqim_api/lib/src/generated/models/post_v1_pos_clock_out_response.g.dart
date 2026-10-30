// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_clock_out_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosClockOutResponse _$PostV1PosClockOutResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosClockOutResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data191.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosClockOutResponseToJson(
  PostV1PosClockOutResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
