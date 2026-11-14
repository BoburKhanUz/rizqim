// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal22.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal22 _$Fiscal22FromJson(Map<String, dynamic> json) => Fiscal22(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt3.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal22ToJson(Fiscal22 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
