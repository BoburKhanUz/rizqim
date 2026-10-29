// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'card_mode.dart';

part 'terminal_flow.g.dart';

@JsonSerializable()
class TerminalFlow {
  const TerminalFlow({
    required this.cardMode,
    required this.smartKassa,
    required this.fiscalKassaId,
    required this.askBankTotal,
    required this.sharedTerminal,
    required this.checklistComplete,
  });

  factory TerminalFlow.fromJson(Map<String, Object?> json) =>
      _$TerminalFlowFromJson(json);

  @JsonKey(name: 'card_mode')
  final CardMode? cardMode;
  @JsonKey(name: 'smart_kassa')
  final bool smartKassa;
  @JsonKey(name: 'fiscal_kassa_id')
  final String? fiscalKassaId;
  @JsonKey(name: 'ask_bank_total')
  final bool askBankTotal;
  @JsonKey(name: 'shared_terminal')
  final bool sharedTerminal;
  @JsonKey(name: 'checklist_complete')
  final bool checklistComplete;

  Map<String, Object?> toJson() => _$TerminalFlowToJson(this);
}
