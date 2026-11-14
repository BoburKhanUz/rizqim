// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'taken10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Taken10 _$Taken10FromJson(Map<String, dynamic> json) => Taken10(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
  card: json['card'] as String,
  qr: json['qr'] as String,
);

Map<String, dynamic> _$Taken10ToJson(Taken10 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
  'card': instance.card,
  'qr': instance.qr,
};
