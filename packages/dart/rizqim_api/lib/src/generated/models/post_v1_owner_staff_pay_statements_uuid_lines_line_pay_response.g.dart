// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_staff_pay_statements_uuid_lines_line_pay_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerStaffPayStatementsUuidLinesLinePayResponse
_$PostV1OwnerStaffPayStatementsUuidLinesLinePayResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerStaffPayStatementsUuidLinesLinePayResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data153.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic>
_$PostV1OwnerStaffPayStatementsUuidLinesLinePayResponseToJson(
  PostV1OwnerStaffPayStatementsUuidLinesLinePayResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
