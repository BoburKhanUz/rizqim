// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order15.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Order15 _$Order15FromJson(Map<String, dynamic> json) => Order15(
  uuid: json['uuid'] as String,
  localNumber: json['local_number'] as String?,
  shortCode: json['short_code'] as String?,
  tableRef: json['table_ref'] as String?,
  status: json['status'] as String,
);

Map<String, dynamic> _$Order15ToJson(Order15 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'local_number': instance.localNumber,
  'short_code': instance.shortCode,
  'table_ref': instance.tableRef,
  'status': instance.status,
};
