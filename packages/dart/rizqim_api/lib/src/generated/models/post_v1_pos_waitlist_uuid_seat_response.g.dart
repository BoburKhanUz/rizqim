// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_waitlist_uuid_seat_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosWaitlistUuidSeatResponse _$PostV1PosWaitlistUuidSeatResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosWaitlistUuidSeatResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data256.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosWaitlistUuidSeatResponseToJson(
  PostV1PosWaitlistUuidSeatResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
