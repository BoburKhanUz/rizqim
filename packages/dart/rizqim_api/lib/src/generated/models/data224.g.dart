// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data224.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data224 _$Data224FromJson(Map<String, dynamic> json) => Data224(
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts.fromJson(e as Map<String, dynamic>))
      .toList(),
  meta: Meta2.fromJson(json['meta'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data224ToJson(Data224 instance) => <String, dynamic>{
  'accounts': instance.accounts,
  'meta': instance.meta,
};
