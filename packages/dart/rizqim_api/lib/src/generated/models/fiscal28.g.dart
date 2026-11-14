// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal28.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal28 _$Fiscal28FromJson(Map<String, dynamic> json) => Fiscal28(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt9.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal28ToJson(Fiscal28 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
