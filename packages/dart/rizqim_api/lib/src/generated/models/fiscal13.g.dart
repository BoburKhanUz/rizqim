// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal13.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal13 _$Fiscal13FromJson(Map<String, dynamic> json) => Fiscal13(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt12.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal13ToJson(Fiscal13 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
