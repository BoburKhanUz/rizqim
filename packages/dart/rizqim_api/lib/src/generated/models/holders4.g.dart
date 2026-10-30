// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders4 _$Holders4FromJson(Map<String, dynamic> json) => Holders4(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff11.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Holders4ToJson(Holders4 instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
