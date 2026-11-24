// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_analytics_events_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1AnalyticsEventsResponse _$PostV1AnalyticsEventsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1AnalyticsEventsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1AnalyticsEventsResponseToJson(
  PostV1AnalyticsEventsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
