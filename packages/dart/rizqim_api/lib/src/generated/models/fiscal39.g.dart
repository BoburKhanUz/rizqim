// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal39.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal39 _$Fiscal39FromJson(Map<String, dynamic> json) => Fiscal39(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt18.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal39ToJson(Fiscal39 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
