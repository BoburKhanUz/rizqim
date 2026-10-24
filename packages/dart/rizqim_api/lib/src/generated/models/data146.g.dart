// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data146.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data146 _$Data146FromJson(Map<String, dynamic> json) => Data146(
  account: Account3.fromJson(json['account'] as Map<String, dynamic>),
  entry: Entry2.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal8.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data146ToJson(Data146 instance) => <String, dynamic>{
  'account': instance.account,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
