// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data158.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data158 _$Data158FromJson(Map<String, dynamic> json) => Data158(
  branchId: (json['branch_id'] as num).toInt(),
  enabled: json['enabled'] as bool,
  locked: json['locked'] as bool,
  orderTypes: (json['order_types'] as List<dynamic>)
      .map((e) => OrderTypes2.fromJson(e as Map<String, dynamic>))
      .toList(),
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data158ToJson(Data158 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'enabled': instance.enabled,
  'locked': instance.locked,
  'order_types': instance.orderTypes,
  'rows': instance.rows,
};
