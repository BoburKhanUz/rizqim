// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data162.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data162 _$Data162FromJson(Map<String, dynamic> json) => Data162(
  totalDebt: json['total_debt'] as String,
  count: (json['count'] as num).toInt(),
  accounts: (json['accounts'] as List<dynamic>)
      .map((e) => Accounts2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data162ToJson(Data162 instance) => <String, dynamic>{
  'total_debt': instance.totalDebt,
  'count': instance.count,
  'accounts': instance.accounts,
};
