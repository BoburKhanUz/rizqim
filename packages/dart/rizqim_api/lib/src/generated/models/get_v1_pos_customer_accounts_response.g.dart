// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_customer_accounts_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosCustomerAccountsResponse _$GetV1PosCustomerAccountsResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosCustomerAccountsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data208.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosCustomerAccountsResponseToJson(
  GetV1PosCustomerAccountsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
