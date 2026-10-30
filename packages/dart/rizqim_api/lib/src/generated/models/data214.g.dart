// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data214.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data214 _$Data214FromJson(Map<String, dynamic> json) => Data214(
  account: Account3.fromJson(json['account'] as Map<String, dynamic>),
  entry: Entry2.fromJson(json['entry'] as Map<String, dynamic>),
  fiscal: Fiscal10.fromJson(json['fiscal'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data214ToJson(Data214 instance) => <String, dynamic>{
  'account': instance.account,
  'entry': instance.entry,
  'fiscal': instance.fiscal,
};
