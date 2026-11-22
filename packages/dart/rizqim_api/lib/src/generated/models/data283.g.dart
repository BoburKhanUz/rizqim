// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data283.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data283 _$Data283FromJson(Map<String, dynamic> json) => Data283(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data283ToJson(Data283 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
