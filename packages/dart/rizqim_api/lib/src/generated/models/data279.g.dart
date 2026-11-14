// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data279.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data279 _$Data279FromJson(Map<String, dynamic> json) => Data279(
  account: Account2.fromJson(json['account'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries9.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data279ToJson(Data279 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
