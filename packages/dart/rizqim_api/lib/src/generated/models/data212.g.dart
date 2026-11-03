// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data212.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data212 _$Data212FromJson(Map<String, dynamic> json) => Data212(
  account: Account.fromJson(json['account'] as String),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data212ToJson(Data212 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
