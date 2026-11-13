// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data135.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data135 _$Data135FromJson(Map<String, dynamic> json) => Data135(
  branchId: (json['branch_id'] as num).toInt(),
  enabled: json['enabled'] as bool,
  locked: json['locked'] as bool,
  orderTypes: (json['order_types'] as List<dynamic>)
      .map((e) => OrderTypes2.fromJson(e as Map<String, dynamic>))
      .toList(),
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data135ToJson(Data135 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'enabled': instance.enabled,
  'locked': instance.locked,
  'order_types': instance.orderTypes,
  'rows': instance.rows,
};
