// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_shifts_uuid_close_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosShiftsUuidCloseResponse _$PostV1PosShiftsUuidCloseResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosShiftsUuidCloseResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data202.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosShiftsUuidCloseResponseToJson(
  PostV1PosShiftsUuidCloseResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
