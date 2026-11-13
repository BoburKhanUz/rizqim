// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data217.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data217 _$Data217FromJson(Map<String, dynamic> json) => Data217(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data217ToJson(Data217 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
