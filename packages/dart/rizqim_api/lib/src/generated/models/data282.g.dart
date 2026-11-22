// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data282.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data282 _$Data282FromJson(Map<String, dynamic> json) => Data282(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data282ToJson(Data282 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
