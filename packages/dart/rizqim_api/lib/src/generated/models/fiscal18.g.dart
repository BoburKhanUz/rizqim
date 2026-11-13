// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal18.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal18 _$Fiscal18FromJson(Map<String, dynamic> json) => Fiscal18(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt15.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal18ToJson(Fiscal18 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
