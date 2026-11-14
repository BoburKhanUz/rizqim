// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rows13.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rows13 _$Rows13FromJson(Map<String, dynamic> json) => Rows13(
  uuid: json['uuid'] as String,
  tender: json['tender'] as String,
  amount: json['amount'] as String,
  paidAt: json['paid_at'] as String?,
);

Map<String, dynamic> _$Rows13ToJson(Rows13 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'tender': instance.tender,
  'amount': instance.amount,
  'paid_at': instance.paidAt,
};
