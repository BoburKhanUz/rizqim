// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data83.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data83 _$Data83FromJson(Map<String, dynamic> json) => Data83(
  fiscal: Fiscal.fromJson(json['fiscal'] as Map<String, dynamic>),
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

Map<String, dynamic> _$Data83ToJson(Data83 instance) => <String, dynamic>{
  'fiscal': instance.fiscal,
  'card_modes': instance.cardModes,
  'checklist_items': instance.checklistItems,
  'branches': instance.branches,
};
