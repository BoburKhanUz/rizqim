// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Order11 _$Order11FromJson(Map<String, dynamic> json) => Order11(
  uuid: json['uuid'] as String,
  localNumber: json['local_number'] as String?,
  shortCode: json['short_code'] as String?,
  tableRef: json['table_ref'] as String?,
  status: json['status'] as String,
);

Map<String, dynamic> _$Order11ToJson(Order11 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'local_number': instance.localNumber,
  'short_code': instance.shortCode,
  'table_ref': instance.tableRef,
  'status': instance.status,
};
