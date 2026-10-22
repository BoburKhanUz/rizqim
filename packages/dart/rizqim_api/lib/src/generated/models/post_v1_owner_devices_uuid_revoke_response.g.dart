// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_devices_uuid_revoke_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerDevicesUuidRevokeResponse
_$PostV1OwnerDevicesUuidRevokeResponseFromJson(Map<String, dynamic> json) =>
    PostV1OwnerDevicesUuidRevokeResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data49.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OwnerDevicesUuidRevokeResponseToJson(
  PostV1OwnerDevicesUuidRevokeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
