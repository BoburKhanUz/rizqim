// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders11 _$Holders11FromJson(Map<String, dynamic> json) => Holders11(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff29.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Holders11ToJson(Holders11 instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
