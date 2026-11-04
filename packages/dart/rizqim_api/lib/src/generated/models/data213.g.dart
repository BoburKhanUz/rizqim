// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data213.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data213 _$Data213FromJson(Map<String, dynamic> json) => Data213(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data213ToJson(Data213 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
