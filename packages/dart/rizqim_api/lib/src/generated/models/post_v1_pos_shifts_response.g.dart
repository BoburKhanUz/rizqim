// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_shifts_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosShiftsResponse _$PostV1PosShiftsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosShiftsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data173.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosShiftsResponseToJson(
  PostV1PosShiftsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
