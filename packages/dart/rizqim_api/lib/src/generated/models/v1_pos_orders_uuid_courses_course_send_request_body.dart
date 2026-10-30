// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_orders_uuid_courses_course_send_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersUuidCoursesCourseSendRequestBody {
  const V1PosOrdersUuidCoursesCourseSendRequestBody({
    required this.version,
    this.approvalToken,
  });

  factory V1PosOrdersUuidCoursesCourseSendRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosOrdersUuidCoursesCourseSendRequestBodyFromJson(json);

  final int version;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosOrdersUuidCoursesCourseSendRequestBodyToJson(this);
}
