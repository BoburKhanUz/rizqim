// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders12.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders12 _$Holders12FromJson(Map<String, dynamic> json) => Holders12(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff33.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Holders12ToJson(Holders12 instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
