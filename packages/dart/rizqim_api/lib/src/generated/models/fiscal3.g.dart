// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal3 _$Fiscal3FromJson(Map<String, dynamic> json) => Fiscal3(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal3ToJson(Fiscal3 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
