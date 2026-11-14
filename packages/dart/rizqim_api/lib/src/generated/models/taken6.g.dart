// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'taken6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Taken6 _$Taken6FromJson(Map<String, dynamic> json) => Taken6(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
  card: json['card'] as String,
  qr: json['qr'] as String,
);

Map<String, dynamic> _$Taken6ToJson(Taken6 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
  'card': instance.card,
  'qr': instance.qr,
};
