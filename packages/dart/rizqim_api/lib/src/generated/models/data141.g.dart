// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data141.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data141 _$Data141FromJson(Map<String, dynamic> json) => Data141(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data141ToJson(Data141 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
