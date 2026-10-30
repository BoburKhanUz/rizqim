// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data46.dart';

part 'put_v1_owner_branches_branch_packaging_rules_rule_response.g.dart';

@JsonSerializable()
class PutV1OwnerBranchesBranchPackagingRulesRuleResponse {
  const PutV1OwnerBranchesBranchPackagingRulesRuleResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerBranchesBranchPackagingRulesRuleResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerBranchesBranchPackagingRulesRuleResponseFromJson(json);

  final bool success;
  final String message;
  final Data46 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerBranchesBranchPackagingRulesRuleResponseToJson(this);
}
