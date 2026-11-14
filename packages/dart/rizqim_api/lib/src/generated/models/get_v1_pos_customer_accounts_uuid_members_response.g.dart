// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_pos_customer_accounts_uuid_members_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1PosCustomerAccountsUuidMembersResponse
_$GetV1PosCustomerAccountsUuidMembersResponseFromJson(
  Map<String, dynamic> json,
) => GetV1PosCustomerAccountsUuidMembersResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data283.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1PosCustomerAccountsUuidMembersResponseToJson(
  GetV1PosCustomerAccountsUuidMembersResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
