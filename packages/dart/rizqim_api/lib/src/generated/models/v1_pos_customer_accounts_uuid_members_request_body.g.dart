// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_customer_accounts_uuid_members_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosCustomerAccountsUuidMembersRequestBody
_$V1PosCustomerAccountsUuidMembersRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosCustomerAccountsUuidMembersRequestBody(
  name: json['name'] as String,
  phone: json['phone'] as String?,
  code: json['code'] as String?,
  dailyLimit: json['daily_limit'] as num?,
  mealsPerDay: (json['meals_per_day'] as num?)?.toInt(),
  note: json['note'] as String?,
);

Map<String, dynamic> _$V1PosCustomerAccountsUuidMembersRequestBodyToJson(
  V1PosCustomerAccountsUuidMembersRequestBody instance,
) => <String, dynamic>{
  'name': instance.name,
  'phone': instance.phone,
  'code': instance.code,
  'daily_limit': instance.dailyLimit,
  'meals_per_day': instance.mealsPerDay,
  'note': instance.note,
};
