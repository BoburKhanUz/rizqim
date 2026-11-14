// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rows6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rows6 _$Rows6FromJson(Map<String, dynamic> json) => Rows6(
  uuid: json['uuid'] as String,
  tender: json['tender'] as String,
  amount: json['amount'] as String,
  paidAt: json['paid_at'] as String?,
);

Map<String, dynamic> _$Rows6ToJson(Rows6 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'tender': instance.tender,
  'amount': instance.amount,
  'paid_at': instance.paidAt,
};
