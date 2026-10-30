// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data208.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data208 _$Data208FromJson(Map<String, dynamic> json) => Data208(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data208ToJson(Data208 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
