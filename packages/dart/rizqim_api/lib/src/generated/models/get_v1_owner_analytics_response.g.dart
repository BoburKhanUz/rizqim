// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_analytics_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerAnalyticsResponse _$GetV1OwnerAnalyticsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerAnalyticsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data45.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerAnalyticsResponseToJson(
  GetV1OwnerAnalyticsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
