// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'extra_bills2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExtraBills2 _$ExtraBills2FromJson(Map<String, dynamic> json) => ExtraBills2(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  total: json['total'] as String,
  paidAmount: json['paid_amount'] as String,
);

Map<String, dynamic> _$ExtraBills2ToJson(ExtraBills2 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'status': instance.status,
      'total': instance.total,
      'paid_amount': instance.paidAmount,
    };
