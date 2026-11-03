// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'post_v1_owner_branches_branch_packaging_rules_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PostV1OwnerBranchesBranchPackagingRulesResponse
_$PostV1OwnerBranchesBranchPackagingRulesResponseFromJson(
  Map<String, dynamic> json,
) => PostV1OwnerBranchesBranchPackagingRulesResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data45.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PostV1OwnerBranchesBranchPackagingRulesResponseToJson(
  PostV1OwnerBranchesBranchPackagingRulesResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
