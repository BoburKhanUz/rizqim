// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunded3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunded3 _$Refunded3FromJson(Map<String, dynamic> json) => Refunded3(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Refunded3ToJson(Refunded3 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
