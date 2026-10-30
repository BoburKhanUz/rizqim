// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'put_v1_owner_branches_branch_packaging_rules_rule_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PutV1OwnerBranchesBranchPackagingRulesRuleResponse
_$PutV1OwnerBranchesBranchPackagingRulesRuleResponseFromJson(
  Map<String, dynamic> json,
) => PutV1OwnerBranchesBranchPackagingRulesRuleResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data46.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic> _$PutV1OwnerBranchesBranchPackagingRulesRuleResponseToJson(
  PutV1OwnerBranchesBranchPackagingRulesRuleResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
