// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data221.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data221 _$Data221FromJson(Map<String, dynamic> json) => Data221(
  account: Account3.fromJson(json['account'] as Map<String, dynamic>),
  entry: Entry2.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal12.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data221ToJson(Data221 instance) => <String, dynamic>{
  'account': instance.account,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
