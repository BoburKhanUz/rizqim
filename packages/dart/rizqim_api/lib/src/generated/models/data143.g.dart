// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data143.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data143 _$Data143FromJson(Map<String, dynamic> json) => Data143(
  account: Account.fromJson(json['account'] as String),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data143ToJson(Data143 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
