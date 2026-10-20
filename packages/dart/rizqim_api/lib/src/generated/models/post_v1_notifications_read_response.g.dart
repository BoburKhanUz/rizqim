// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_notifications_read_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1NotificationsReadResponse _$PostV1NotificationsReadResponseFromJson(
  Map<String, dynamic> json,
) => PostV1NotificationsReadResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$PostV1NotificationsReadResponseToJson(
  PostV1NotificationsReadResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
