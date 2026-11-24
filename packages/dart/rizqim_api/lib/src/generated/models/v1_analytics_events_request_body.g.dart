// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_analytics_events_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1AnalyticsEventsRequestBody _$V1AnalyticsEventsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1AnalyticsEventsRequestBody(
  sessionId: json['session_id'] as String,
  platform: json['platform'] as String,
  events: (json['events'] as List<dynamic>)
      .map((e) => Events.fromJson(e as Map<String, dynamic>))
      .toList(),
  appVersion: json['app_version'] as String?,
  startParam: json['start_param'] as String?,
);

Map<String, dynamic> _$V1AnalyticsEventsRequestBodyToJson(
  V1AnalyticsEventsRequestBody instance,
) => <String, dynamic>{
  'session_id': instance.sessionId,
  'platform': instance.platform,
  'app_version': instance.appVersion,
  'start_param': instance.startParam,
  'events': instance.events,
};
