// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data43.dart';

part 'put_v1_owner_branches_branch_terminal_flow_response.g.dart';

@JsonSerializable()
class PutV1OwnerBranchesBranchTerminalFlowResponse {
  const PutV1OwnerBranchesBranchTerminalFlowResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerBranchesBranchTerminalFlowResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerBranchesBranchTerminalFlowResponseFromJson(json);

  final bool success;
  final String message;
  final Data43 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerBranchesBranchTerminalFlowResponseToJson(this);
}
