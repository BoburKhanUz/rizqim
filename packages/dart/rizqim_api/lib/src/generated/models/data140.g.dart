// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data140.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data140 _$Data140FromJson(Map<String, dynamic> json) => Data140(
  account: Account.fromJson(json['account'] as String),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data140ToJson(Data140 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
