// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal15.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal15 _$Fiscal15FromJson(Map<String, dynamic> json) => Fiscal15(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt14.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal15ToJson(Fiscal15 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
