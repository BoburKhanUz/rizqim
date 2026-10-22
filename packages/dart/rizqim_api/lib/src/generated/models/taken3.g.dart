// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'taken3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Taken3 _$Taken3FromJson(Map<String, dynamic> json) => Taken3(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Taken3ToJson(Taken3 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
