// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'taken2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Taken2 _$Taken2FromJson(Map<String, dynamic> json) => Taken2(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Taken2ToJson(Taken2 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
