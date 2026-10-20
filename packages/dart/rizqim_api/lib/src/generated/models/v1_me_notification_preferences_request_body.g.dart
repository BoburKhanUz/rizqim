// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_me_notification_preferences_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1MeNotificationPreferencesRequestBody
_$V1MeNotificationPreferencesRequestBodyFromJson(Map<String, dynamic> json) =>
    V1MeNotificationPreferencesRequestBody(
      preferences: (json['preferences'] as List<dynamic>)
          .map((e) => e as String)
          .toList(),
    );

Map<String, dynamic> _$V1MeNotificationPreferencesRequestBodyToJson(
  V1MeNotificationPreferencesRequestBody instance,
) => <String, dynamic>{'preferences': instance.preferences};
