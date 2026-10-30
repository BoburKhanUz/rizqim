// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders10 _$Holders10FromJson(Map<String, dynamic> json) => Holders10(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff27.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Holders10ToJson(Holders10 instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
