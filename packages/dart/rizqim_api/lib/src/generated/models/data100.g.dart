// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data100.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data100 _$Data100FromJson(Map<String, dynamic> json) => Data100(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  name: json['name'] as String,
  kind: json['kind'] as String,
  kindLabel: json['kind_label'] as String,
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
  showOnKds: json['show_on_kds'] as bool,
);

Map<String, dynamic> _$Data100ToJson(Data100 instance) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'name': instance.name,
  'kind': instance.kind,
  'kind_label': instance.kindLabel,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'show_on_kds': instance.showOnKds,
};
