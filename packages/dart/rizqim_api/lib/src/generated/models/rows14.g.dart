// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rows14.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rows14 _$Rows14FromJson(Map<String, dynamic> json) => Rows14(
  uuid: json['uuid'] as String,
  tender: json['tender'] as String,
  amount: json['amount'] as String,
  paidAt: json['paid_at'] as String?,
);

Map<String, dynamic> _$Rows14ToJson(Rows14 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'tender': instance.tender,
  'amount': instance.amount,
  'paid_at': instance.paidAt,
};
