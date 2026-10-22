// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal10 _$Fiscal10FromJson(Map<String, dynamic> json) => Fiscal10(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt9.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal10ToJson(Fiscal10 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
