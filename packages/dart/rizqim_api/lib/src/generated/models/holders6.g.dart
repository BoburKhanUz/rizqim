// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders6 _$Holders6FromJson(Map<String, dynamic> json) => Holders6(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff16.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Holders6ToJson(Holders6 instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
