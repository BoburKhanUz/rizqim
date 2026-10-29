// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_waitlist_uuid_notify_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosWaitlistUuidNotifyResponse
_$PostV1PosWaitlistUuidNotifyResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosWaitlistUuidNotifyResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data255.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosWaitlistUuidNotifyResponseToJson(
  PostV1PosWaitlistUuidNotifyResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
