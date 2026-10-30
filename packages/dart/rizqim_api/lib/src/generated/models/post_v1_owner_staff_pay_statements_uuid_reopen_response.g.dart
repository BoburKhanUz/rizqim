// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_staff_pay_statements_uuid_reopen_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerStaffPayStatementsUuidReopenResponse
_$PostV1OwnerStaffPayStatementsUuidReopenResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerStaffPayStatementsUuidReopenResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data155.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerStaffPayStatementsUuidReopenResponseToJson(
  PostV1OwnerStaffPayStatementsUuidReopenResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
