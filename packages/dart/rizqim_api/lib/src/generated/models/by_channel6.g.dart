// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_channel6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByChannel6 _$ByChannel6FromJson(Map<String, dynamic> json) => ByChannel6(
  channel: json['channel'] as String,
  orders: (json['orders'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByChannel6ToJson(ByChannel6 instance) =>
    <String, dynamic>{
      'channel': instance.channel,
      'orders': instance.orders,
      'amount': instance.amount,
    };
