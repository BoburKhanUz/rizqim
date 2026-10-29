// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_branches_branch_terminal_flow_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBranchesBranchTerminalFlowRequestBody
_$V1OwnerBranchesBranchTerminalFlowRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBranchesBranchTerminalFlowRequestBody(
  checklist: json['checklist'] == null
      ? null
      : Checklist2.fromJson(json['checklist'] as Map<String, dynamic>),
  cardMode: json['card_mode'] == null
      ? null
      : CardMode.fromJson(json['card_mode'] as String?),
  differenceThreshold: json['difference_threshold'] as String?,
  askBankTotal: json['ask_bank_total'] as bool?,
  sharedTerminal: json['shared_terminal'] as bool?,
);

Map<String, dynamic> _$V1OwnerBranchesBranchTerminalFlowRequestBodyToJson(
  V1OwnerBranchesBranchTerminalFlowRequestBody instance,
) => <String, dynamic>{
  'checklist': instance.checklist,
  'card_mode': instance.cardMode,
  'difference_threshold': instance.differenceThreshold,
  'ask_bank_total': instance.askBankTotal,
  'shared_terminal': instance.sharedTerminal,
};
