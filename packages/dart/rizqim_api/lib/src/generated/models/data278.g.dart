// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data278.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data278 _$Data278FromJson(Map<String, dynamic> json) => Data278(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data278ToJson(Data278 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
