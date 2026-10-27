// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_customer_accounts_uuid_adjustments_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosCustomerAccountsUuidAdjustmentsResponse
_$PostV1PosCustomerAccountsUuidAdjustmentsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosCustomerAccountsUuidAdjustmentsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data145.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosCustomerAccountsUuidAdjustmentsResponseToJson(
  PostV1PosCustomerAccountsUuidAdjustmentsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
