// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal32.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal32 _$Fiscal32FromJson(Map<String, dynamic> json) => Fiscal32(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt13.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal32ToJson(Fiscal32 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
