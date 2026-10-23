// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tables5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tables5 _$Tables5FromJson(Map<String, dynamic> json) => Tables5(
  id: (json['id'] as num).toInt(),
  hallId: (json['hall_id'] as num).toInt(),
  name: json['name'] as String,
  kind: json['kind'] as String,
  seats: (json['seats'] as num?)?.toInt(),
  layout: Layout11.fromJson(json['layout'] as Map<String, dynamic>),
  sortOrder: (json['sort_order'] as num).toInt(),
  minSpend: json['min_spend'] as String?,
  minSpendMode: json['min_spend_mode'] as String,
);

Map<String, dynamic> _$Tables5ToJson(Tables5 instance) => <String, dynamic>{
  'id': instance.id,
  'hall_id': instance.hallId,
  'name': instance.name,
  'kind': instance.kind,
  'seats': instance.seats,
  'layout': instance.layout,
  'sort_order': instance.sortOrder,
  'min_spend': instance.minSpend,
  'min_spend_mode': instance.minSpendMode,
};
