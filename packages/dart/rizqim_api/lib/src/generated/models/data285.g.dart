// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data285.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data285 _$Data285FromJson(Map<String, dynamic> json) => Data285(
  account: Account.fromJson(json['account'] as String),
  member: Member.fromJson(json['member'] as Map<String, dynamic>),
  usage: Usage.fromJson(json['usage'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data285ToJson(Data285 instance) => <String, dynamic>{
  'account': instance.account,
  'member': instance.member,
  'usage': instance.usage,
};
