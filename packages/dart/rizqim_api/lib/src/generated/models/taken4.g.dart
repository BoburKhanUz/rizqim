// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'taken4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Taken4 _$Taken4FromJson(Map<String, dynamic> json) => Taken4(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Taken4ToJson(Taken4 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
