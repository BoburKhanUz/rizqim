// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sold4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sold4 _$Sold4FromJson(Map<String, dynamic> json) => Sold4(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
  card: json['card'] as String,
  qr: json['qr'] as String,
);

Map<String, dynamic> _$Sold4ToJson(Sold4 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
  'card': instance.card,
  'qr': instance.qr,
};
