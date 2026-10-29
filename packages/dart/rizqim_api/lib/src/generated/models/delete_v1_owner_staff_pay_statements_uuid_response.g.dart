// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_staff_pay_statements_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerStaffPayStatementsUuidResponse
_$DeleteV1OwnerStaffPayStatementsUuidResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerStaffPayStatementsUuidResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: json['data'],
);

Map<String, dynamic> _$DeleteV1OwnerStaffPayStatementsUuidResponseToJson(
  DeleteV1OwnerStaffPayStatementsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
