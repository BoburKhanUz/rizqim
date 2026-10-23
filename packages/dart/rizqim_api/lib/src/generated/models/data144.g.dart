// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data144.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data144 _$Data144FromJson(Map<String, dynamic> json) => Data144(
  account: Account3.fromJson(json['account'] as Map<String, dynamic>),
  entry: Entry2.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal8.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data144ToJson(Data144 instance) => <String, dynamic>{
  'account': instance.account,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
