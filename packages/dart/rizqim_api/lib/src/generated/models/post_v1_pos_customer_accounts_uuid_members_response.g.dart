// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_customer_accounts_uuid_members_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosCustomerAccountsUuidMembersResponse
_$PostV1PosCustomerAccountsUuidMembersResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosCustomerAccountsUuidMembersResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data233.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosCustomerAccountsUuidMembersResponseToJson(
  PostV1PosCustomerAccountsUuidMembersResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
