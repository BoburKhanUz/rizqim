// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'checklist_items.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ChecklistItems _$ChecklistItemsFromJson(Map<String, dynamic> json) =>
    ChecklistItems(
      key: json['key'] as String,
      label: json['label'] as String,
      hint: json['hint'] as String,
    );

Map<String, dynamic> _$ChecklistItemsToJson(ChecklistItems instance) =>
    <String, dynamic>{
      'key': instance.key,
      'label': instance.label,
      'hint': instance.hint,
    };
