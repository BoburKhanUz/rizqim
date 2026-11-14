// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'extra_bills3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExtraBills3 _$ExtraBills3FromJson(Map<String, dynamic> json) => ExtraBills3(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  total: json['total'] as String,
  paidAmount: json['paid_amount'] as String,
);

Map<String, dynamic> _$ExtraBills3ToJson(ExtraBills3 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'status': instance.status,
      'total': instance.total,
      'paid_amount': instance.paidAmount,
    };
