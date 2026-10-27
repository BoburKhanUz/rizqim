// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data163.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data163 _$Data163FromJson(Map<String, dynamic> json) => Data163(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data163ToJson(Data163 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
