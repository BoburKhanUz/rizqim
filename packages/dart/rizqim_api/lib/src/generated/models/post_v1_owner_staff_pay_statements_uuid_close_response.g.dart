// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_staff_pay_statements_uuid_close_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerStaffPayStatementsUuidCloseResponse
_$PostV1OwnerStaffPayStatementsUuidCloseResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerStaffPayStatementsUuidCloseResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data151.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerStaffPayStatementsUuidCloseResponseToJson(
  PostV1OwnerStaffPayStatementsUuidCloseResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
