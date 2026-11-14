// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data101.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data101 _$Data101FromJson(Map<String, dynamic> json) => Data101(
  fiscal: Fiscal2.fromJson(json['fiscal'] as Map<String, dynamic>),
  cardModes: (json['card_modes'] as List<dynamic>)
      .map((e) => CardModes.fromJson(e as Map<String, dynamic>))
      .toList(),
  checklistItems: (json['checklist_items'] as List<dynamic>)
      .map((e) => ChecklistItems.fromJson(e as Map<String, dynamic>))
      .toList(),
  branches: (json['branches'] as List<dynamic>)
      .map((e) => Branches3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data101ToJson(Data101 instance) => <String, dynamic>{
  'fiscal': instance.fiscal,
  'card_modes': instance.cardModes,
  'checklist_items': instance.checklistItems,
  'branches': instance.branches,
};
