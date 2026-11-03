// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data45.dart';

part 'post_v1_owner_branches_branch_packaging_rules_response.g.dart';

@JsonSerializable()
class PostV1OwnerBranchesBranchPackagingRulesResponse {
  const PostV1OwnerBranchesBranchPackagingRulesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerBranchesBranchPackagingRulesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerBranchesBranchPackagingRulesResponseFromJson(json);

  final bool success;
  final String message;
  final Data45 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerBranchesBranchPackagingRulesResponseToJson(this);
}
