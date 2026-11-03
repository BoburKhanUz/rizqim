// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal17.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal17 _$Fiscal17FromJson(Map<String, dynamic> json) => Fiscal17(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt14.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal17ToJson(Fiscal17 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
