// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal9 _$Fiscal9FromJson(Map<String, dynamic> json) => Fiscal9(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt8.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal9ToJson(Fiscal9 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
