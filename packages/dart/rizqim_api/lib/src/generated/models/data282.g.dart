// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data282.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data282 _$Data282FromJson(Map<String, dynamic> json) => Data282(
  account: Account2.fromJson(json['account'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data282ToJson(Data282 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
