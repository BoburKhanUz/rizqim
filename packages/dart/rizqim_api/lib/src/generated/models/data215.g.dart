// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data215.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data215 _$Data215FromJson(Map<String, dynamic> json) => Data215(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data215ToJson(Data215 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
