// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal5 _$Fiscal5FromJson(Map<String, dynamic> json) => Fiscal5(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt2.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal5ToJson(Fiscal5 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
