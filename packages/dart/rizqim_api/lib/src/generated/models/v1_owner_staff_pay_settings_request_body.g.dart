// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_staff_pay_settings_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerStaffPaySettingsRequestBody _$V1OwnerStaffPaySettingsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerStaffPaySettingsRequestBody(
  period: json['period'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1OwnerStaffPaySettingsRequestBodyToJson(
  V1OwnerStaffPaySettingsRequestBody instance,
) => <String, dynamic>{
  'branch_id': instance.branchId,
  'period': instance.period,
};
