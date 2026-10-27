// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_pos_customer_accounts_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1PosCustomerAccountsUuidResponse
_$PatchV1PosCustomerAccountsUuidResponseFromJson(Map<String, dynamic> json) =>
    PatchV1PosCustomerAccountsUuidResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data165.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PatchV1PosCustomerAccountsUuidResponseToJson(
  PatchV1PosCustomerAccountsUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
