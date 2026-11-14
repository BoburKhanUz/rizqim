// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunded11.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunded11 _$Refunded11FromJson(Map<String, dynamic> json) => Refunded11(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Refunded11ToJson(Refunded11 instance) =>
    <String, dynamic>{
      'count': instance.count,
      'amount': instance.amount,
      'cash': instance.cash,
    };
