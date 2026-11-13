// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_customer_accounts_uuid_members_bulk_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosCustomerAccountsUuidMembersBulkRequestBody
_$V1PosCustomerAccountsUuidMembersBulkRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosCustomerAccountsUuidMembersBulkRequestBody(
  lines: json['lines'] as String,
  dailyLimit: json['daily_limit'] as num?,
  mealsPerDay: (json['meals_per_day'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosCustomerAccountsUuidMembersBulkRequestBodyToJson(
  V1PosCustomerAccountsUuidMembersBulkRequestBody instance,
) => <String, dynamic>{
  'lines': instance.lines,
  'daily_limit': instance.dailyLimit,
  'meals_per_day': instance.mealsPerDay,
};
