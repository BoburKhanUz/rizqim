// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal19.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal19 _$Fiscal19FromJson(Map<String, dynamic> json) => Fiscal19(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt12.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal19ToJson(Fiscal19 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
