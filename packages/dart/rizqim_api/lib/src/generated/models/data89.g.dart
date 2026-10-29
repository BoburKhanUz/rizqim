// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data89.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data89 _$Data89FromJson(Map<String, dynamic> json) => Data89(
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

Map<String, dynamic> _$Data89ToJson(Data89 instance) => <String, dynamic>{
  'id': instance.id,
  'branch_id': instance.branchId,
  'name': instance.name,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'service_fee': instance.serviceFee,
  'tables': instance.tables,
};
