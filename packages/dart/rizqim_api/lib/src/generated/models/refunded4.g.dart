// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunded4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunded4 _$Refunded4FromJson(Map<String, dynamic> json) => Refunded4(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Refunded4ToJson(Refunded4 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
