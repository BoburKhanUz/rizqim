// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunded2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunded2 _$Refunded2FromJson(Map<String, dynamic> json) => Refunded2(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Refunded2ToJson(Refunded2 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
