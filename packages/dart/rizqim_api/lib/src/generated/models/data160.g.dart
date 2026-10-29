// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data160.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data160 _$Data160FromJson(Map<String, dynamic> json) => Data160(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data160ToJson(Data160 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
