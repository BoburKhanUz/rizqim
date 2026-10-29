// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_staff_pay_statements_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerStaffPayStatementsResponse
_$PostV1OwnerStaffPayStatementsResponseFromJson(Map<String, dynamic> json) =>
    PostV1OwnerStaffPayStatementsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data149.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PostV1OwnerStaffPayStatementsResponseToJson(
  PostV1OwnerStaffPayStatementsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
