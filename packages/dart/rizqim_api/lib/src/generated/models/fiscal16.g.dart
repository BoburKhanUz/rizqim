// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal16.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal16 _$Fiscal16FromJson(Map<String, dynamic> json) => Fiscal16(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt13.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal16ToJson(Fiscal16 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
