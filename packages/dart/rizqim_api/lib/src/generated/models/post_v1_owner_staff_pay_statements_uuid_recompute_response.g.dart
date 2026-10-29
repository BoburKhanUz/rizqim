// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_staff_pay_statements_uuid_recompute_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerStaffPayStatementsUuidRecomputeResponse
_$PostV1OwnerStaffPayStatementsUuidRecomputeResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerStaffPayStatementsUuidRecomputeResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data154.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerStaffPayStatementsUuidRecomputeResponseToJson(
  PostV1OwnerStaffPayStatementsUuidRecomputeResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
