// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data94.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data94 _$Data94FromJson(Map<String, dynamic> json) => Data94(
  id: (json['id'] as num).toInt(),
  branchId: (json['branch_id'] as num).toInt(),
  name: json['name'] as String,
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
  serviceFee: json['service_fee'] == null
      ? null
      : ServiceFee3.fromJson(json['service_fee'] as Map<String, dynamic>),
  tables: (json['tables'] as List<dynamic>)
      .map((e) => Tables4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data94ToJson(Data94 instance) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'name': instance.name,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'service_fee': instance.serviceFee,
  'tables': instance.tables,
};
