// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_staff_pay_statements_uuid_lines_line_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerStaffPayStatementsUuidLinesLineResponse
_$PutV1OwnerStaffPayStatementsUuidLinesLineResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerStaffPayStatementsUuidLinesLineResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data168.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerStaffPayStatementsUuidLinesLineResponseToJson(
  PutV1OwnerStaffPayStatementsUuidLinesLineResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
