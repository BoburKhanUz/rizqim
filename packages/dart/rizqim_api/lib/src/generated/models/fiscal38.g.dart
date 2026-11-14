// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal38.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal38 _$Fiscal38FromJson(Map<String, dynamic> json) => Fiscal38(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt17.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal38ToJson(Fiscal38 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
