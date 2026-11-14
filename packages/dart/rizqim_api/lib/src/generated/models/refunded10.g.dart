// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunded10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunded10 _$Refunded10FromJson(Map<String, dynamic> json) => Refunded10(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Refunded10ToJson(Refunded10 instance) =>
    <String, dynamic>{
      'count': instance.count,
      'amount': instance.amount,
      'cash': instance.cash,
    };
