// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal36.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal36 _$Fiscal36FromJson(Map<String, dynamic> json) => Fiscal36(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt17.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal36ToJson(Fiscal36 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
