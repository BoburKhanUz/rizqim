// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data47.dart';

part 'delete_v1_owner_branches_branch_packaging_rules_rule_response.g.dart';

@JsonSerializable()
class DeleteV1OwnerBranchesBranchPackagingRulesRuleResponse {
  const DeleteV1OwnerBranchesBranchPackagingRulesRuleResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory DeleteV1OwnerBranchesBranchPackagingRulesRuleResponse.fromJson(
    Map<String, Object?> json,
  ) => _$DeleteV1OwnerBranchesBranchPackagingRulesRuleResponseFromJson(json);

  final bool success;
  final String message;
  final Data47 data;

  Map<String, Object?> toJson() =>
      _$DeleteV1OwnerBranchesBranchPackagingRulesRuleResponseToJson(this);
}
