// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order13.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Order13 _$Order13FromJson(Map<String, dynamic> json) => Order13(
  uuid: json['uuid'] as String,
  localNumber: json['local_number'] as String?,
  shortCode: json['short_code'] as String?,
  tableRef: json['table_ref'] as String?,
  status: json['status'] as String,
);

Map<String, dynamic> _$Order13ToJson(Order13 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'local_number': instance.localNumber,
  'short_code': instance.shortCode,
  'table_ref': instance.tableRef,
  'status': instance.status,
};
