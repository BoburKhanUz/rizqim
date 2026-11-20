// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data290.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data290 _$Data290FromJson(Map<String, dynamic> json) => Data290(
  account: Account5.fromJson(json['account'] as Map<String, dynamic>),
  entry: Entry4.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal32.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data290ToJson(Data290 instance) => <String, dynamic>{
  'account': instance.account,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
