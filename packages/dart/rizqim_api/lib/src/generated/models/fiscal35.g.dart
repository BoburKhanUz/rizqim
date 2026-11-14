// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal35.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal35 _$Fiscal35FromJson(Map<String, dynamic> json) => Fiscal35(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt16.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal35ToJson(Fiscal35 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
