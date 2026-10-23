// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal14.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal14 _$Fiscal14FromJson(Map<String, dynamic> json) => Fiscal14(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt13.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal14ToJson(Fiscal14 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
