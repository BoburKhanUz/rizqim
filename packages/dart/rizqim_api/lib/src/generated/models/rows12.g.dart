// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rows12.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rows12 _$Rows12FromJson(Map<String, dynamic> json) => Rows12(
  uuid: json['uuid'] as String,
  tender: json['tender'] as String,
  amount: json['amount'] as String,
  paidAt: json['paid_at'] as String?,
);

Map<String, dynamic> _$Rows12ToJson(Rows12 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'tender': instance.tender,
  'amount': instance.amount,
  'paid_at': instance.paidAt,
};
