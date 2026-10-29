// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'terminal_flow.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

TerminalFlow _$TerminalFlowFromJson(Map<String, dynamic> json) => TerminalFlow(
  cardMode: json['card_mode'] == null
      ? null
      : CardMode.fromJson(json['card_mode'] as String?),
  smartKassa: json['smart_kassa'] as bool,
  fiscalKassaId: json['fiscal_kassa_id'] as String?,
  askBankTotal: json['ask_bank_total'] as bool,
  sharedTerminal: json['shared_terminal'] as bool,
  checklistComplete: json['checklist_complete'] as bool,
);

Map<String, dynamic> _$TerminalFlowToJson(TerminalFlow instance) =>
    <String, dynamic>{
      'card_mode': instance.cardMode,
      'smart_kassa': instance.smartKassa,
      'fiscal_kassa_id': instance.fiscalKassaId,
      'ask_bank_total': instance.askBankTotal,
      'shared_terminal': instance.sharedTerminal,
      'checklist_complete': instance.checklistComplete,
    };
