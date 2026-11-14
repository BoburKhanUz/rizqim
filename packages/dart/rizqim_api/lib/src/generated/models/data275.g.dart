// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data275.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data275 _$Data275FromJson(Map<String, dynamic> json) => Data275(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data275ToJson(Data275 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
