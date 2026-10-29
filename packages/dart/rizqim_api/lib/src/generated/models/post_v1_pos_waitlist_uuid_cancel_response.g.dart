// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_waitlist_uuid_cancel_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosWaitlistUuidCancelResponse
_$PostV1PosWaitlistUuidCancelResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosWaitlistUuidCancelResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data253.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosWaitlistUuidCancelResponseToJson(
  PostV1PosWaitlistUuidCancelResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
