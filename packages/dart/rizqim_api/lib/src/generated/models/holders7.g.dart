// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders7 _$Holders7FromJson(Map<String, dynamic> json) => Holders7(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff18.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Holders7ToJson(Holders7 instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
