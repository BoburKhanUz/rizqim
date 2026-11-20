// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data280.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data280 _$Data280FromJson(Map<String, dynamic> json) => Data280(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data280ToJson(Data280 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
