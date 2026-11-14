// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data117.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data117 _$Data117FromJson(Map<String, dynamic> json) => Data117(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  name: json['name'] as String,
  kind: json['kind'] as String,
  kindLabel: json['kind_label'] as String,
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
  showOnKds: json['show_on_kds'] as bool,
  targetSeconds: (json['target_seconds'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data117ToJson(Data117 instance) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'name': instance.name,
  'kind': instance.kind,
  'kind_label': instance.kindLabel,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'show_on_kds': instance.showOnKds,
  'target_seconds': instance.targetSeconds,
};
