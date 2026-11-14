// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data280.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data280 _$Data280FromJson(Map<String, dynamic> json) => Data280(
  account: Account2.fromJson(json['account'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data280ToJson(Data280 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
