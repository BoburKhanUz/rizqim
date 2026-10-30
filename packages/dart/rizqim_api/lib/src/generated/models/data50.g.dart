// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data50.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data50 _$Data50FromJson(Map<String, dynamic> json) => Data50(
  branchId: (json['branch_id'] as num).toInt(),
  cardMode: json['card_mode'] == null
      ? null
      : CardMode.fromJson(json['card_mode'] as String?),
  cardModeLabel: json['card_mode_label'] as String?,
  checklist: (json['checklist'] as List<dynamic>)
      .map((e) => Checklist.fromJson(e as Map<String, dynamic>))
      .toList(),
  checklistComplete: json['checklist_complete'] as bool,
  differenceThreshold: json['difference_threshold'] as String,
  askBankTotal: json['ask_bank_total'] as bool,
  sharedTerminal: json['shared_terminal'] as bool,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$Data50ToJson(Data50 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'card_mode': instance.cardMode,
  'card_mode_label': instance.cardModeLabel,
  'checklist': instance.checklist,
  'checklist_complete': instance.checklistComplete,
  'difference_threshold': instance.differenceThreshold,
  'ask_bank_total': instance.askBankTotal,
  'shared_terminal': instance.sharedTerminal,
  'updated_at': instance.updatedAt,
};
