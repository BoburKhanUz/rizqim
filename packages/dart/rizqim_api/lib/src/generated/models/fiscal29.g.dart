// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal29.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal29 _$Fiscal29FromJson(Map<String, dynamic> json) => Fiscal29(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt10.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal29ToJson(Fiscal29 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
