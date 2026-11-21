// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data279.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data279 _$Data279FromJson(Map<String, dynamic> json) => Data279(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data279ToJson(Data279 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
