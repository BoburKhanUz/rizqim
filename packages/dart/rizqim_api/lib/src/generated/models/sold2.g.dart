// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sold2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sold2 _$Sold2FromJson(Map<String, dynamic> json) => Sold2(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
  card: json['card'] as String,
  qr: json['qr'] as String,
);

Map<String, dynamic> _$Sold2ToJson(Sold2 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
  'card': instance.card,
  'qr': instance.qr,
};
