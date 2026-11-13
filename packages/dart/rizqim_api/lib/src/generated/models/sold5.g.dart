// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sold5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sold5 _$Sold5FromJson(Map<String, dynamic> json) => Sold5(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
  card: json['card'] as String,
  qr: json['qr'] as String,
);

Map<String, dynamic> _$Sold5ToJson(Sold5 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
  'card': instance.card,
  'qr': instance.qr,
};
