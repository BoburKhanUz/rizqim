// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data278.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data278 _$Data278FromJson(Map<String, dynamic> json) => Data278(
  account: Account.fromJson(json['account'] as String),
  member: Member.fromJson(json['member'] as Map<String, dynamic>),
  usage: Usage.fromJson(json['usage'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data278ToJson(Data278 instance) => <String, dynamic>{
  'account': instance.account,
  'member': instance.member,
  'usage': instance.usage,
};
