// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data228.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data228 _$Data228FromJson(Map<String, dynamic> json) => Data228(
  account: Account2.fromJson(json['account'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries4.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data228ToJson(Data228 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
