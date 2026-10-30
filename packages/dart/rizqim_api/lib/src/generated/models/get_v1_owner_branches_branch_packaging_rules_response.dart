// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data44.dart';

part 'get_v1_owner_branches_branch_packaging_rules_response.g.dart';

@JsonSerializable()
class GetV1OwnerBranchesBranchPackagingRulesResponse {
  const GetV1OwnerBranchesBranchPackagingRulesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerBranchesBranchPackagingRulesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1OwnerBranchesBranchPackagingRulesResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data44> data;

  Map<String, Object?> toJson() =>
      _$GetV1OwnerBranchesBranchPackagingRulesResponseToJson(this);
}
