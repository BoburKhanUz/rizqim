// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal37.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal37 _$Fiscal37FromJson(Map<String, dynamic> json) => Fiscal37(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt18.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal37ToJson(Fiscal37 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
