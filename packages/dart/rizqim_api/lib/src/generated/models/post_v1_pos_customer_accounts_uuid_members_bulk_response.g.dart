// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_pos_customer_accounts_uuid_members_bulk_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1PosCustomerAccountsUuidMembersBulkResponse
_$PostV1PosCustomerAccountsUuidMembersBulkResponseFromJson(
  Map<String, dynamic> json,
) => PostV1PosCustomerAccountsUuidMembersBulkResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data234.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1PosCustomerAccountsUuidMembersBulkResponseToJson(
  PostV1PosCustomerAccountsUuidMembersBulkResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
