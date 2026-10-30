// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data128.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data128 _$Data128FromJson(Map<String, dynamic> json) => Data128(
  branchId: (json['branch_id'] as num).toInt(),
  enabled: json['enabled'] as bool,
  locked: json['locked'] as bool,
  orderTypes: (json['order_types'] as List<dynamic>)
      .map((e) => OrderTypes.fromJson(e as Map<String, dynamic>))
      .toList(),
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data128ToJson(Data128 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'enabled': instance.enabled,
  'locked': instance.locked,
  'order_types': instance.orderTypes,
  'rows': instance.rows,
};
