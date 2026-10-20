// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'groups.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Groups _$GroupsFromJson(Map<String, dynamic> json) => Groups(
  modifierGroupId: (json['modifier_group_id'] as num).toInt(),
  minSelect: (json['min_select'] as num?)?.toInt(),
  maxSelect: (json['max_select'] as num?)?.toInt(),
  isRequired: json['is_required'] as bool?,
  sortOrder: (json['sort_order'] as num?)?.toInt(),
);

Map<String, dynamic> _$GroupsToJson(Groups instance) => <String, dynamic>{
  'modifier_group_id': instance.modifierGroupId,
  'min_select': instance.minSelect,
  'max_select': instance.maxSelect,
  'is_required': instance.isRequired,
  'sort_order': instance.sortOrder,
};
