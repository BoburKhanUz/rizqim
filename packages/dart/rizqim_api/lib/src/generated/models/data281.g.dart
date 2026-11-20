// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data281.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data281 _$Data281FromJson(Map<String, dynamic> json) => Data281(
  account: Account.fromJson(json['account'] as String),
  member: Member.fromJson(json['member'] as Map<String, dynamic>),
  usage: Usage.fromJson(json['usage'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data281ToJson(Data281 instance) => <String, dynamic>{
  'account': instance.account,
  'member': instance.member,
  'usage': instance.usage,
};
