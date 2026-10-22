// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tables.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tables _$TablesFromJson(Map<String, dynamic> json) => Tables(
  id: (json['id'] as num).toInt(),
  layout: Layout5.fromJson(json['layout'] as Map<String, dynamic>),
  hallId: (json['hall_id'] as num?)?.toInt(),
  sortOrder: (json['sort_order'] as num?)?.toInt(),
);

Map<String, dynamic> _$TablesToJson(Tables instance) => <String, dynamic>{
  'id': instance.id,
  'hall_id': instance.hallId,
  'sort_order': instance.sortOrder,
  'layout': instance.layout,
};
