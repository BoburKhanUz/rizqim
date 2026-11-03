// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data215.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data215 _$Data215FromJson(Map<String, dynamic> json) => Data215(
  account: Account3.fromJson(json['account'] as Map<String, dynamic>),
  entry: Entry2.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal10.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data215ToJson(Data215 instance) => <String, dynamic>{
  'account': instance.account,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
