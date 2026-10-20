// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_devices_uuid_push_token_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1DevicesUuidPushTokenResponse _$PutV1DevicesUuidPushTokenResponseFromJson(
  Map<String, dynamic> json,
) => PutV1DevicesUuidPushTokenResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data12.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1DevicesUuidPushTokenResponseToJson(
  PutV1DevicesUuidPushTokenResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
