// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'repayments3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Repayments3 _$Repayments3FromJson(Map<String, dynamic> json) => Repayments3(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Repayments3ToJson(Repayments3 instance) =>
    <String, dynamic>{
      'count': instance.count,
      'amount': instance.amount,
      'cash': instance.cash,
    };
