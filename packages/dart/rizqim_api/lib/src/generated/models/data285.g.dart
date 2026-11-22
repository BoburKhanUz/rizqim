// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data285.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data285 _$Data285FromJson(Map<String, dynamic> json) => Data285(
  account: Account2.fromJson(json['account'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data285ToJson(Data285 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
