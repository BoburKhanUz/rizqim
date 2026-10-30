// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders9 _$Holders9FromJson(Map<String, dynamic> json) => Holders9(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff25.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Holders9ToJson(Holders9 instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
