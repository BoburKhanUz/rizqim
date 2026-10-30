// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_customer_accounts_uuid_statement_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosCustomerAccountsUuidStatementResponse
_$GetV1PosCustomerAccountsUuidStatementResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosCustomerAccountsUuidStatementResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data199.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosCustomerAccountsUuidStatementResponseToJson(
  GetV1PosCustomerAccountsUuidStatementResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
