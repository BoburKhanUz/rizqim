// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_staff_pay_rules_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerStaffPayRulesResponse _$PostV1OwnerStaffPayRulesResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerStaffPayRulesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data145.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerStaffPayRulesResponseToJson(
  PostV1OwnerStaffPayRulesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
