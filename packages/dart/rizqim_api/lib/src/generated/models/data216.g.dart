// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data216.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data216 _$Data216FromJson(Map<String, dynamic> json) => Data216(
  account: Account.fromJson(json['account'] as String),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data216ToJson(Data216 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
