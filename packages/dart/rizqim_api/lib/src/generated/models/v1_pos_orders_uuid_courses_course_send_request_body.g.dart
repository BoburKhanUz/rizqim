// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_orders_uuid_courses_course_send_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosOrdersUuidCoursesCourseSendRequestBody
_$V1PosOrdersUuidCoursesCourseSendRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosOrdersUuidCoursesCourseSendRequestBody(
  version: (json['version'] as num).toInt(),
  approvalToken: json['approval_token'] as String?,
);

Map<String, dynamic> _$V1PosOrdersUuidCoursesCourseSendRequestBodyToJson(
  V1PosOrdersUuidCoursesCourseSendRequestBody instance,
) => <String, dynamic>{
  'version': instance.version,
  'approval_token': instance.approvalToken,
};
