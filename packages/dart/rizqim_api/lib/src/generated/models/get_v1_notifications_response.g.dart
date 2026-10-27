// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_notifications_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1NotificationsResponse _$GetV1NotificationsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1NotificationsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data22.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1NotificationsResponseToJson(
  GetV1NotificationsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
