// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'fiscal33.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Fiscal33 _$Fiscal33FromJson(Map<String, dynamic> json) => Fiscal33(
  status: json['status'] as String?,
  receiptUuid: json['receipt_uuid'] as String?,
  error: json['error'] as String?,
  receipt: json['receipt'] == null
      ? null
      : Receipt14.fromJson(json['receipt'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Fiscal33ToJson(Fiscal33 instance) => <String, dynamic>{
  'status': instance.status,
  'receipt_uuid': instance.receiptUuid,
  'error': instance.error,
  'receipt': instance.receipt,
};
