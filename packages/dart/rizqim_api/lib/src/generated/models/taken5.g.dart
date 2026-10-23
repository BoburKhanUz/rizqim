// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'taken5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Taken5 _$Taken5FromJson(Map<String, dynamic> json) => Taken5(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Taken5ToJson(Taken5 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
