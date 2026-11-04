// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data215.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data215 _$Data215FromJson(Map<String, dynamic> json) => Data215(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data215ToJson(Data215 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
