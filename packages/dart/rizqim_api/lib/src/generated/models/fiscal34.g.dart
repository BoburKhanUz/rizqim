// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal34.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal34 _$Fiscal34FromJson(Map<String, dynamic> json) => Fiscal34(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt15.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal34ToJson(Fiscal34 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
