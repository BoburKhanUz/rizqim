// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'refunded9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Refunded9 _$Refunded9FromJson(Map<String, dynamic> json) => Refunded9(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Refunded9ToJson(Refunded9 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
