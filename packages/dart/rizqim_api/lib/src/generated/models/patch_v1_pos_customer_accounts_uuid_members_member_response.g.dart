// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'patch_v1_pos_customer_accounts_uuid_members_member_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PatchV1PosCustomerAccountsUuidMembersMemberResponse
_$PatchV1PosCustomerAccountsUuidMembersMemberResponseFromJson(
  Map<String, dynamic> json,
) => PatchV1PosCustomerAccountsUuidMembersMemberResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data286.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic>
_$PatchV1PosCustomerAccountsUuidMembersMemberResponseToJson(
  PatchV1PosCustomerAccountsUuidMembersMemberResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
