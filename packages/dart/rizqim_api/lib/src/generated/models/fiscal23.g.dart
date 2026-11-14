// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal23.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal23 _$Fiscal23FromJson(Map<String, dynamic> json) => Fiscal23(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt4.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal23ToJson(Fiscal23 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
