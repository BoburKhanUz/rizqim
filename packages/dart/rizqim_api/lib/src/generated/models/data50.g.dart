// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data50.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data50 _$Data50FromJson(Map<String, dynamic> json) => Data50(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  hallId: (json['hall_id'] as num).toInt(),
  name: json['name'] as String,
  kind: json['kind'] as String,
  seats: (json['seats'] as num?)?.toInt(),
  layout: Layout.fromJson(json['layout'] as Map<String, dynamic>),
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
  minSpend: json['min_spend'] as String?,
  minSpendMode: json['min_spend_mode'] as String,
);

Map<String, dynamic> _$Data50ToJson(Data50 instance) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'hall_id': instance.hallId,
  'name': instance.name,
  'kind': instance.kind,
  'seats': instance.seats,
  'layout': instance.layout,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'min_spend': instance.minSpend,
  'min_spend_mode': instance.minSpendMode,
};
