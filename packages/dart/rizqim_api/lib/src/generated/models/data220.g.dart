// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data220.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data220 _$Data220FromJson(Map<String, dynamic> json) => Data220(
  account: Account2.fromJson(json['account'] as Map<String, dynamic>),
  entry: Entry.fromJson(json['entry'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data220ToJson(Data220 instance) => <String, dynamic>{
  'account': instance.account,
  'entry': instance.entry,
};
