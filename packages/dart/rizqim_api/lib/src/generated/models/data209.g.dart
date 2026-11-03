// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data209.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data209 _$Data209FromJson(Map<String, dynamic> json) => Data209(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data209ToJson(Data209 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
