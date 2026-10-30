// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_customer_accounts_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosCustomerAccountsResponse _$PostV1PosCustomerAccountsResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosCustomerAccountsResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data193.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosCustomerAccountsResponseToJson(
  PostV1PosCustomerAccountsResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
