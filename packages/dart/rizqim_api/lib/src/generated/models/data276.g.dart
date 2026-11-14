// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data276.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data276 _$Data276FromJson(Map<String, dynamic> json) => Data276(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data276ToJson(Data276 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
