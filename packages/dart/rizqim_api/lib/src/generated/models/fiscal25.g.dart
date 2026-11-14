// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal25.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal25 _$Fiscal25FromJson(Map<String, dynamic> json) => Fiscal25(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt6.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal25ToJson(Fiscal25 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
