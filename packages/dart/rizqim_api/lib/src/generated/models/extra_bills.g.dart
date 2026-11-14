// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'extra_bills.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ExtraBills _$ExtraBillsFromJson(Map<String, dynamic> json) => ExtraBills(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  total: json['total'] as String,
  paidAmount: json['paid_amount'] as String,
);

Map<String, dynamic> _$ExtraBillsToJson(ExtraBills instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'status': instance.status,
      'total': instance.total,
      'paid_amount': instance.paidAmount,
    };
