// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'repayments5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Repayments5 _$Repayments5FromJson(Map<String, dynamic> json) => Repayments5(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Repayments5ToJson(Repayments5 instance) =>
    <String, dynamic>{
      'count': instance.count,
      'amount': instance.amount,
      'cash': instance.cash,
    };
