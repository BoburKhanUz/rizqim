// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal27.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal27 _$Fiscal27FromJson(Map<String, dynamic> json) => Fiscal27(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt8.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal27ToJson(Fiscal27 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
