// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_customer_accounts_uuid_repayments_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosCustomerAccountsUuidRepaymentsResponse
_$PostV1PosCustomerAccountsUuidRepaymentsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosCustomerAccountsUuidRepaymentsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data143.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosCustomerAccountsUuidRepaymentsResponseToJson(
  PostV1PosCustomerAccountsUuidRepaymentsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
