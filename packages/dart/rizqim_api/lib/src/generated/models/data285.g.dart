// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data285.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data285 _$Data285FromJson(Map<String, dynamic> json) => Data285(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data285ToJson(Data285 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
