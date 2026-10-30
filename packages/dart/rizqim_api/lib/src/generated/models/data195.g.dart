// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data195.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data195 _$Data195FromJson(Map<String, dynamic> json) => Data195(
  account: Account.fromJson(json['account'] as String),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data195ToJson(Data195 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
