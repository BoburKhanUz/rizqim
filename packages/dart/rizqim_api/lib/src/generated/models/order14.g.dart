// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order14.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Order14 _$Order14FromJson(Map<String, dynamic> json) => Order14(
  uuid: json['uuid'] as String,
  localNumber: json['local_number'] as String?,
  shortCode: json['short_code'] as String?,
  tableRef: json['table_ref'] as String?,
  status: json['status'] as String,
);

Map<String, dynamic> _$Order14ToJson(Order14 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'local_number': instance.localNumber,
  'short_code': instance.shortCode,
  'table_ref': instance.tableRef,
  'status': instance.status,
};
