// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_waitlist_uuid_no_show_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosWaitlistUuidNoShowResponse
_$PostV1PosWaitlistUuidNoShowResponseFromJson(Map<String, dynamic> json) =>
    PostV1PosWaitlistUuidNoShowResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data254.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1PosWaitlistUuidNoShowResponseToJson(
  PostV1PosWaitlistUuidNoShowResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
