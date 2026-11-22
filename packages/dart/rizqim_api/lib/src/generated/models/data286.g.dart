// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data286.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data286 _$Data286FromJson(Map<String, dynamic> json) => Data286(
  account: Account2.fromJson(json['account'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data286ToJson(Data286 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
