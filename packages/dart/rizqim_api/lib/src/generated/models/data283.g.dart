// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data283.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data283 _$Data283FromJson(Map<String, dynamic> json) => Data283(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data283ToJson(Data283 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
