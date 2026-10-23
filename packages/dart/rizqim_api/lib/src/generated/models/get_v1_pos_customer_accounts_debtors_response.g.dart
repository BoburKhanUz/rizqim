// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_customer_accounts_debtors_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosCustomerAccountsDebtorsResponse
_$GetV1PosCustomerAccountsDebtorsResponseFromJson(Map<String, dynamic> json) =>
    GetV1PosCustomerAccountsDebtorsResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data140.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$GetV1PosCustomerAccountsDebtorsResponseToJson(
  GetV1PosCustomerAccountsDebtorsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
