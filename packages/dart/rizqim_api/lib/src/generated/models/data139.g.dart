// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data139.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data139 _$Data139FromJson(Map<String, dynamic> json) => Data139(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data139ToJson(Data139 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
