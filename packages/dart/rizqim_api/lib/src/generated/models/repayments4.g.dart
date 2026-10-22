// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'repayments4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Repayments4 _$Repayments4FromJson(Map<String, dynamic> json) => Repayments4(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Repayments4ToJson(Repayments4 instance) =>
    <String, dynamic>{
      'count': instance.count,
      'amount': instance.amount,
      'cash': instance.cash,
    };
