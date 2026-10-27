// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_me_notification_preferences_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1MeNotificationPreferencesResponse
_$GetV1MeNotificationPreferencesResponseFromJson(Map<String, dynamic> json) =>
    GetV1MeNotificationPreferencesResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data40.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1MeNotificationPreferencesResponseToJson(
  GetV1MeNotificationPreferencesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
