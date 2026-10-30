// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders3 _$Holders3FromJson(Map<String, dynamic> json) => Holders3(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff9.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Holders3ToJson(Holders3 instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
