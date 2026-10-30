// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_orders_uuid_courses_course_send_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosOrdersUuidCoursesCourseSendResponse
_$PostV1PosOrdersUuidCoursesCourseSendResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosOrdersUuidCoursesCourseSendResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data216.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosOrdersUuidCoursesCourseSendResponseToJson(
  PostV1PosOrdersUuidCoursesCourseSendResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
