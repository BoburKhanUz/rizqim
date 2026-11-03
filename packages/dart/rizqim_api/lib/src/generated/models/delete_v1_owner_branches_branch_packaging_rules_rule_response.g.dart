// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delete_v1_owner_branches_branch_packaging_rules_rule_response.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

DeleteV1OwnerBranchesBranchPackagingRulesRuleResponse
_$DeleteV1OwnerBranchesBranchPackagingRulesRuleResponseFromJson(
  Map<String, dynamic> json,
) => DeleteV1OwnerBranchesBranchPackagingRulesRuleResponse(
  success: json['success'] as bool,
  message: json['message'] as String,
  data: Data47.fromJson(json['data'] as Map<String, dynamic>),
);

Map<String, dynamic>
_$DeleteV1OwnerBranchesBranchPackagingRulesRuleResponseToJson(
  DeleteV1OwnerBranchesBranchPackagingRulesRuleResponse instance,
) => <String, dynamic>{
  'success': instance.success,
  'message': instance.message,
  'data': instance.data,
};
