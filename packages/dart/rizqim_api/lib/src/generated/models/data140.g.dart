// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data140.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data140 _$Data140FromJson(Map<String, dynamic> json) => Data140(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data140ToJson(Data140 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
