// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal7 _$Fiscal7FromJson(Map<String, dynamic> json) => Fiscal7(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt6.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal7ToJson(Fiscal7 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
