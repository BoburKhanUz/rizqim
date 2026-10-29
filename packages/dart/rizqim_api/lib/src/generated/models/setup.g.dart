// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'setup.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Setup _$SetupFromJson(Map<String, dynamic> json) => Setup(
  branchId: (json['branch_id'] as num).toInt(),
  cardMode: json['card_mode'] == null
      ? null
      : CardMode.fromJson(json['card_mode'] as String?),
  cardModeLabel: json['card_mode_label'] as String?,
  checklist: (json['checklist'] as List<dynamic>)
      .map((e) => Checklist3.fromJson(e as Map<String, dynamic>))
      .toList(),
  checklistComplete: json['checklist_complete'] as bool,
  differenceThreshold: json['difference_threshold'] as String,
  askBankTotal: json['ask_bank_total'] as bool,
  sharedTerminal: json['shared_terminal'] as bool,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$SetupToJson(Setup instance) => <String, dynamic>{
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
