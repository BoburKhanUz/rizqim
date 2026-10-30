// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data211.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data211 _$Data211FromJson(Map<String, dynamic> json) => Data211(
  account: Account.fromJson(json['account'] as String),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data211ToJson(Data211 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
