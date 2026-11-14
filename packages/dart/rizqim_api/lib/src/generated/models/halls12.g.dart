// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'halls12.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Halls12 _$Halls12FromJson(Map<String, dynamic> json) => Halls12(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
  tables: (json['tables'] as List<dynamic>)
      .map((e) => Tables6.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Halls12ToJson(Halls12 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'tables': instance.tables,
};
