// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'holders8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Holders8 _$Holders8FromJson(Map<String, dynamic> json) => Holders8(
  mode: json['mode'] as String,
  staff: json['staff'] == null
      ? null
      : Staff20.fromJson(json['staff'] as Map<String, dynamic>),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$Holders8ToJson(Holders8 instance) => <String, dynamic>{
  'mode': instance.mode,
  'staff': instance.staff,
  'amount': instance.amount,
};
