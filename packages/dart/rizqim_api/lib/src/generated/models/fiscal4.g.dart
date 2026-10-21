// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal4 _$Fiscal4FromJson(Map<String, dynamic> json) => Fiscal4(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
);

Map<String, dynamic> _$Fiscal4ToJson(Fiscal4 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
};
