// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data281.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data281 _$Data281FromJson(Map<String, dynamic> json) => Data281(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data281ToJson(Data281 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
