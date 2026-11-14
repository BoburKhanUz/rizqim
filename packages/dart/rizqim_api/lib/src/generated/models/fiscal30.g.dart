// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal30.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal30 _$Fiscal30FromJson(Map<String, dynamic> json) => Fiscal30(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt11.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal30ToJson(Fiscal30 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
