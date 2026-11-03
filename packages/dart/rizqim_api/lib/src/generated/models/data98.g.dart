// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data98.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data98 _$Data98FromJson(Map<String, dynamic> json) => Data98(
  fiscal: Fiscal2.fromJson(json['fiscal'] as Map<String, dynamic>),
  cardModes: (json['card_modes'] as List<dynamic>)
      .map((e) => CardModes.fromJson(e as Map<String, dynamic>))
      .toList(),
  checklistItems: (json['checklist_items'] as List<dynamic>)
      .map((e) => ChecklistItems.fromJson(e as Map<String, dynamic>))
      .toList(),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data98ToJson(Data98 instance) => <String, dynamic>{
  'fiscal': instance.fiscal,
  'card_modes': instance.cardModes,
  'checklist_items': instance.checklistItems,
  'branches': instance.branches,
};
