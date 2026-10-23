// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunded5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunded5 _$Refunded5FromJson(Map<String, dynamic> json) => Refunded5(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Refunded5ToJson(Refunded5 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
