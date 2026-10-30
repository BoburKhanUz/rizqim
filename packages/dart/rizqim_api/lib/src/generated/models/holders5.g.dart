// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders5 _$Holders5FromJson(Map<String, dynamic> json) => Holders5(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff13.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Holders5ToJson(Holders5 instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
