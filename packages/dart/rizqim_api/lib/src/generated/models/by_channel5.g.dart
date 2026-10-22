// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_channel5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByChannel5 _$ByChannel5FromJson(Map<String, dynamic> json) => ByChannel5(
  channel: json['channel'] as String,
  orders: (json['orders'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByChannel5ToJson(ByChannel5 instance) =>
    <String, dynamic>{
      'channel': instance.channel,
      'orders': instance.orders,
      'amount': instance.amount,
    };
