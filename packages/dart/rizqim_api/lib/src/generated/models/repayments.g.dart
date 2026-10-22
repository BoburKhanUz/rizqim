// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'repayments.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Repayments _$RepaymentsFromJson(Map<String, dynamic> json) => Repayments(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$RepaymentsToJson(Repayments instance) =>
    <String, dynamic>{
      'count': instance.count,
      'amount': instance.amount,
      'cash': instance.cash,
    };
