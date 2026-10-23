// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal6 _$Fiscal6FromJson(Map<String, dynamic> json) => Fiscal6(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt5.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal6ToJson(Fiscal6 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
