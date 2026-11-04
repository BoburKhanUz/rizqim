// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data219.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data219 _$Data219FromJson(Map<String, dynamic> json) => Data219(
  account: Account3.fromJson(json['account'] as Map<String, dynamic>),
  entry: Entry2.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal10.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data219ToJson(Data219 instance) => <String, dynamic>{
  'account': instance.account,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
