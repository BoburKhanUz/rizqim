// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'taken9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Taken9 _$Taken9FromJson(Map<String, dynamic> json) => Taken9(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$Taken9ToJson(Taken9 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
