// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data284.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data284 _$Data284FromJson(Map<String, dynamic> json) => Data284(
  account: Account.fromJson(json['account'] as String),
  member: Member.fromJson(json['member'] as Map<String, dynamic>),
  usage: Usage.fromJson(json['usage'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data284ToJson(Data284 instance) => <String, dynamic>{
  'account': instance.account,
  'member': instance.member,
  'usage': instance.usage,
};
