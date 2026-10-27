// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_me_notification_preferences_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1MeNotificationPreferencesResponse
_$PutV1MeNotificationPreferencesResponseFromJson(Map<String, dynamic> json) =>
    PutV1MeNotificationPreferencesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data20.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1MeNotificationPreferencesResponseToJson(
  PutV1MeNotificationPreferencesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
