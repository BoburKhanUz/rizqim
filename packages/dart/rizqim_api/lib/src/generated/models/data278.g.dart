// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data278.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data278 _$Data278FromJson(Map<String, dynamic> json) => Data278(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data278ToJson(Data278 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
