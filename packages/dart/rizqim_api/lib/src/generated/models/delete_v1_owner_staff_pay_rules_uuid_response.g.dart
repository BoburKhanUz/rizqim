// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_staff_pay_rules_uuid_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerStaffPayRulesUuidResponse
_$DeleteV1OwnerStaffPayRulesUuidResponseFromJson(Map<String, dynamic> json) =>
    DeleteV1OwnerStaffPayRulesUuidResponse(
      success: json['success'] as bool,
      message: json['message'] as String,
      data: json['data'],
    );

Map<String, dynamic> _$DeleteV1OwnerStaffPayRulesUuidResponseToJson(
  DeleteV1OwnerStaffPayRulesUuidResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
