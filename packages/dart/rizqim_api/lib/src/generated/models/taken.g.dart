// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'taken.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Taken _$TakenFromJson(Map<String, dynamic> json) => Taken(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
);

Map<String, dynamic> _$TakenToJson(Taken instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
};
