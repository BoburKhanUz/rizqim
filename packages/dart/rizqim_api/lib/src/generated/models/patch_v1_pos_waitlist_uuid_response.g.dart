// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_pos_waitlist_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1PosWaitlistUuidResponse _$PatchV1PosWaitlistUuidResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1PosWaitlistUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data252.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PatchV1PosWaitlistUuidResponseToJson(
  PatchV1PosWaitlistUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
