// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_clock_in_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosClockInResponse _$PostV1PosClockInResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosClockInResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data190.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosClockInResponseToJson(
  PostV1PosClockInResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
