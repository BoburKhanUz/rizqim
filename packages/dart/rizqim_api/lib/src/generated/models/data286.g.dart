// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data286.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data286 _$Data286FromJson(Map<String, dynamic> json) => Data286(
  account: Account.fromJson(json['account'] as String),
  member: Member.fromJson(json['member'] as Map<String, dynamic>),
  usage: Usage.fromJson(json['usage'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data286ToJson(Data286 instance) => <String, dynamic>{
  'account': instance.account,
  'member': instance.member,
  'usage': instance.usage,
};
