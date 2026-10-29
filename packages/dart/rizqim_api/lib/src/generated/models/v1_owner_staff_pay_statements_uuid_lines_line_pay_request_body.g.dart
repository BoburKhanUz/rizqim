// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_staff_pay_statements_uuid_lines_line_pay_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerStaffPayStatementsUuidLinesLinePayRequestBody
_$V1OwnerStaffPayStatementsUuidLinesLinePayRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerStaffPayStatementsUuidLinesLinePayRequestBody(
  method: json['method'] as String,
  shiftUuid: json['shift_uuid'] as String?,
  note: json['note'] as String?,
);

Map<String, dynamic>
_$V1OwnerStaffPayStatementsUuidLinesLinePayRequestBodyToJson(
  V1OwnerStaffPayStatementsUuidLinesLinePayRequestBody instance,
) => <String, dynamic>{
  'method': instance.method,
  'shift_uuid': instance.shiftUuid,
  'note': instance.note,
};
