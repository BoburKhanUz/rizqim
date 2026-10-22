// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_customer_accounts_uuid_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosCustomerAccountsUuidRequestBody
_$V1PosCustomerAccountsUuidRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosCustomerAccountsUuidRequestBody(
      name: json['name'] as String?,
      phone: json['phone'] as String?,
      groupName: json['group_name'] as String?,
      creditLimit: json['credit_limit'] as num?,
      isBlocked: json['is_blocked'] as bool?,
      note: json['note'] as String?,
      branchId: (json['branch_id'] as num?)?.toInt(),
    );

Map<String, dynamic> _$V1PosCustomerAccountsUuidRequestBodyToJson(
  V1PosCustomerAccountsUuidRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'phone': instance.phone,
  'group_name': instance.groupName,
  'credit_limit': instance.creditLimit,
  'is_blocked': instance.isBlocked,
  'note': instance.note,
  'branch_id': instance.branchId,
};
