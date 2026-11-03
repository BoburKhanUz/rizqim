// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data211.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data211 _$Data211FromJson(Map<String, dynamic> json) => Data211(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data211ToJson(Data211 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
