// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data142.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data142 _$Data142FromJson(Map<String, dynamic> json) => Data142(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data142ToJson(Data142 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
