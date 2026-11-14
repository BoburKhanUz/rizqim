// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'extra_bills4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExtraBills4 _$ExtraBills4FromJson(Map<String, dynamic> json) => ExtraBills4(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  total: json['total'] as String,
  paidAmount: json['paid_amount'] as String,
);

Map<String, dynamic> _$ExtraBills4ToJson(ExtraBills4 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'status': instance.status,
      'total': instance.total,
      'paid_amount': instance.paidAmount,
    };
