// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'halls.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Halls _$HallsFromJson(Map<String, dynamic> json) => Halls(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
  tables: (json['tables'] as List<dynamic>)
      .map((e) => Tables5.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$HallsToJson(Halls instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'tables': instance.tables,
};
