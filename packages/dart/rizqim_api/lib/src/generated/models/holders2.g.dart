// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders2 _$Holders2FromJson(Map<String, dynamic> json) => Holders2(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff5.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Holders2ToJson(Holders2 instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
