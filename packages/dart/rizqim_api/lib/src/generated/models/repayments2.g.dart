// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'repayments2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Repayments2 _$Repayments2FromJson(Map<String, dynamic> json) => Repayments2(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Repayments2ToJson(Repayments2 instance) =>
    <String, dynamic>{
      'count': instance.count,
      'amount': instance.amount,
      'cash': instance.cash,
    };
