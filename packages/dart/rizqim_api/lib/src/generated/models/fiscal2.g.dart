// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal2 _$Fiscal2FromJson(Map<String, dynamic> json) => Fiscal2(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
);

Map<String, dynamic> _$Fiscal2ToJson(Fiscal2 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
};
