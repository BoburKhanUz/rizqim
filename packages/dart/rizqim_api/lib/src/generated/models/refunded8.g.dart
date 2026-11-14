// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunded8.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunded8 _$Refunded8FromJson(Map<String, dynamic> json) => Refunded8(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Refunded8ToJson(Refunded8 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
