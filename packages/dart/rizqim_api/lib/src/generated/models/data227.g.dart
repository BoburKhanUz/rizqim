// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data227.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data227 _$Data227FromJson(Map<String, dynamic> json) => Data227(
  account: Account.fromJson(json['account'] as String),
  member: Member.fromJson(json['member'] as Map<String, dynamic>),
  usage: Usage.fromJson(json['usage'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data227ToJson(Data227 instance) => <String, dynamic>{
  'account': instance.account,
  'member': instance.member,
  'usage': instance.usage,
};
