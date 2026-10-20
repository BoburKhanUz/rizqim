// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_devices_uuid_push_token_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1DevicesUuidPushTokenResponse
_$DeleteV1DevicesUuidPushTokenResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1DevicesUuidPushTokenResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data13.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$DeleteV1DevicesUuidPushTokenResponseToJson(
  DeleteV1DevicesUuidPushTokenResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
