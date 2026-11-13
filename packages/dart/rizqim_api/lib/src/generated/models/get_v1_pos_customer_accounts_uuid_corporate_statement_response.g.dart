// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_customer_accounts_uuid_corporate_statement_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosCustomerAccountsUuidCorporateStatementResponse
_$GetV1PosCustomerAccountsUuidCorporateStatementResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosCustomerAccountsUuidCorporateStatementResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data231.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic>
_$GetV1PosCustomerAccountsUuidCorporateStatementResponseToJson(
  GetV1PosCustomerAccountsUuidCorporateStatementResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
