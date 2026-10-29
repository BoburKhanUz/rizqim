// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'checklist2.dart';
import 'card_mode.dart';

part 'v1_owner_branches_branch_terminal_flow_request_body.g.dart';

@JsonSerializable()
class V1OwnerBranchesBranchTerminalFlowRequestBody {
  const V1OwnerBranchesBranchTerminalFlowRequestBody({
    this.checklist,
    this.cardMode,
    this.differenceThreshold,
    this.askBankTotal,
  });

  factory V1OwnerBranchesBranchTerminalFlowRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerBranchesBranchTerminalFlowRequestBodyFromJson(json);

  final Checklist2? checklist;
  @JsonKey(name: 'card_mode')
  final CardMode? cardMode;
  @JsonKey(name: 'difference_threshold')
  final String? differenceThreshold;
  @JsonKey(name: 'ask_bank_total')
  final bool? askBankTotal;

  Map<String, Object?> toJson() =>
      _$V1OwnerBranchesBranchTerminalFlowRequestBodyToJson(this);
}
