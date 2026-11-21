// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data283.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data283 _$Data283FromJson(Map<String, dynamic> json) => Data283(
  account: Account2.fromJson(json['account'] as Map<String, dynamic>),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data283ToJson(Data283 instance) => <String, dynamic>{
  'account': instance.account,
  'entries': instance.entries,
};
