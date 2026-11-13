// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sold6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sold6 _$Sold6FromJson(Map<String, dynamic> json) => Sold6(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
  card: json['card'] as String,
  qr: json['qr'] as String,
);

Map<String, dynamic> _$Sold6ToJson(Sold6 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
  'card': instance.card,
  'qr': instance.qr,
};
