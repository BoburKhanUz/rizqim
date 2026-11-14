// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'halls11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Halls11 _$Halls11FromJson(Map<String, dynamic> json) => Halls11(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  sortOrder: (json['sort_order'] as num).toInt(),
);

Map<String, dynamic> _$Halls11ToJson(Halls11 instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'sort_order': instance.sortOrder,
};
