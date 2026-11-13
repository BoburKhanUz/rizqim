// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_customer_accounts_uuid_members_member_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosCustomerAccountsUuidMembersMemberRequestBody
_$V1PosCustomerAccountsUuidMembersMemberRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosCustomerAccountsUuidMembersMemberRequestBody(
  name: json['name'] as String?,
  phone: json['phone'] as String?,
  code: json['code'] as String?,
  dailyLimit: json['daily_limit'] as num?,
  mealsPerDay: (json['meals_per_day'] as num?)?.toInt(),
  isActive: json['is_active'] as bool?,
  note: json['note'] as String?,
  regenerateCode: json['regenerate_code'] as bool?,
);

Map<String, dynamic> _$V1PosCustomerAccountsUuidMembersMemberRequestBodyToJson(
  V1PosCustomerAccountsUuidMembersMemberRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'phone': instance.phone,
  'code': instance.code,
  'daily_limit': instance.dailyLimit,
  'meals_per_day': instance.mealsPerDay,
  'is_active': instance.isActive,
  'note': instance.note,
  'regenerate_code': instance.regenerateCode,
};
