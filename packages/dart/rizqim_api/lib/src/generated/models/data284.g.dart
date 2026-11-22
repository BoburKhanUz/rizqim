// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data284.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data284 _$Data284FromJson(Map<String, dynamic> json) => Data284(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data284ToJson(Data284 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
