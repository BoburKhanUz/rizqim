// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data210.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data210 _$Data210FromJson(Map<String, dynamic> json) => Data210(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data210ToJson(Data210 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
