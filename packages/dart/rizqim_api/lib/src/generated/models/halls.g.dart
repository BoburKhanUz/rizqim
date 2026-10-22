// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'halls.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Halls _$HallsFromJson(Map<String, dynamic> json) => Halls(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  sortOrder: (json['sort_order'] as num).toInt(),
);

Map<String, dynamic> _$HallsToJson(Halls instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'sort_order': instance.sortOrder,
};
