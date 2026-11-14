// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data157.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data157 _$Data157FromJson(Map<String, dynamic> json) => Data157(
  branchId: (json['branch_id'] as num).toInt(),
  enabled: json['enabled'] as bool,
  locked: json['locked'] as bool,
  orderTypes: (json['order_types'] as List<dynamic>)
      .map((e) => OrderTypes.fromJson(e as Map<String, dynamic>))
      .toList(),
  rows: (json['rows'] as List<dynamic>)
      .map((e) => Rows2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data157ToJson(Data157 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'enabled': instance.enabled,
  'locked': instance.locked,
  'order_types': instance.orderTypes,
  'rows': instance.rows,
};
