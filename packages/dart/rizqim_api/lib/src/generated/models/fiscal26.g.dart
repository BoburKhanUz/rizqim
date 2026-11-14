// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal26.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal26 _$Fiscal26FromJson(Map<String, dynamic> json) => Fiscal26(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt7.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal26ToJson(Fiscal26 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
