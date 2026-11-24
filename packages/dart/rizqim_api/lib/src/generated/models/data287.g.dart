// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data287.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data287 _$Data287FromJson(Map<String, dynamic> json) => Data287(
  account: Account2.fromJson(json['account'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data287ToJson(Data287 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
