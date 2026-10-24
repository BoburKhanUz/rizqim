// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifier_group_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ModifierGroupRequest _$ModifierGroupRequestFromJson(
  Map<String, dynamic> json,
) => ModifierGroupRequest(
  name: json['name'] as String,
  nameI18n: (json['name_i18n'] as Map<String, dynamic>?)?.map(
    (k, e) => MapEntry(k, e as String),
  ),
  minSelect: (json['min_select'] as num?)?.toInt(),
  maxSelect: (json['max_select'] as num?)?.toInt(),
  isRequired: json['is_required'] as bool?,
  sortOrder: (json['sort_order'] as num?)?.toInt(),
  isActive: json['is_active'] as bool?,
  modifiers: (json['modifiers'] as List<dynamic>?)
      ?.map((e) => Modifiers29.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$ModifierGroupRequestToJson(
  ModifierGroupRequest instance,
) => <String, dynamic>{
  'name': instance.name,
  'name_i18n': instance.nameI18n,
  'min_select': instance.minSelect,
  'max_select': instance.maxSelect,
  'is_required': instance.isRequired,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'modifiers': instance.modifiers,
};
