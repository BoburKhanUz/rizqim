// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data137.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data137 _$Data137FromJson(Map<String, dynamic> json) => Data137(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data137ToJson(Data137 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
