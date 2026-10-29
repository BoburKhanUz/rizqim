// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_staff_pay_rules_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerStaffPayRulesUuidResponse
_$PutV1OwnerStaffPayRulesUuidResponseFromJson(Map<String, dynamic> json) =>
    PutV1OwnerStaffPayRulesUuidResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: Data146.fromJson(json['data'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PutV1OwnerStaffPayRulesUuidResponseToJson(
  PutV1OwnerStaffPayRulesUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
