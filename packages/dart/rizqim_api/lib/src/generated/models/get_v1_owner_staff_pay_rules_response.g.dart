// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'get_v1_owner_staff_pay_rules_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

GetV1OwnerStaffPayRulesResponse _$GetV1OwnerStaffPayRulesResponseFromJson(
  Map<String, dynamic> json,
) => GetV1OwnerStaffPayRulesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data160.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$GetV1OwnerStaffPayRulesResponseToJson(
  GetV1OwnerStaffPayRulesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
