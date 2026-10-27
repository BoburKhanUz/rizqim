// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data161.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data161 _$Data161FromJson(Map<String, dynamic> json) => Data161(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data161ToJson(Data161 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
