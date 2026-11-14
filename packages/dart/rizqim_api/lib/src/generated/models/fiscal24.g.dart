// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal24.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal24 _$Fiscal24FromJson(Map<String, dynamic> json) => Fiscal24(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt5.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal24ToJson(Fiscal24 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
