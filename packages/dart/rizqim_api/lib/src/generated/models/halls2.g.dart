// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'halls2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Halls2 _$Halls2FromJson(Map<String, dynamic> json) => Halls2(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
  tables: (json['tables'] as List<dynamic>)
      .map((e) => Tables6.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Halls2ToJson(Halls2 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'tables': instance.tables,
};
