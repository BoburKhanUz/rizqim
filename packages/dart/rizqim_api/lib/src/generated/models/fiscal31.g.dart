// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal31.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal31 _$Fiscal31FromJson(Map<String, dynamic> json) => Fiscal31(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt12.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal31ToJson(Fiscal31 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
