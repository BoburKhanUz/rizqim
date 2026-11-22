// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data281.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data281 _$Data281FromJson(Map<String, dynamic> json) => Data281(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data281ToJson(Data281 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
