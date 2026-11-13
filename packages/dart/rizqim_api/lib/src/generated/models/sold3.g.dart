// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'sold3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sold3 _$Sold3FromJson(Map<String, dynamic> json) => Sold3(
  count: (json['count'] as num).toInt(),
  amount: json['amount'] as String,
  cash: json['cash'] as String,
  card: json['card'] as String,
  qr: json['qr'] as String,
);

Map<String, dynamic> _$Sold3ToJson(Sold3 instance) => <String, dynamic>{
  'count': instance.count,
  'amount': instance.amount,
  'cash': instance.cash,
  'card': instance.card,
  'qr': instance.qr,
};
