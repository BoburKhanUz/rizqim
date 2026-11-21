// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data282.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data282 _$Data282FromJson(Map<String, dynamic> json) => Data282(
  account: Account.fromJson(json['account'] as String),
  member: Member.fromJson(json['member'] as Map<String, dynamic>),
  usage: Usage.fromJson(json['usage'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data282ToJson(Data282 instance) => <String, dynamic>{
  'account': instance.account,
  'member': instance.member,
  'usage': instance.usage,
};
