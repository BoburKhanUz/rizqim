// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_channel3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByChannel3 _$ByChannel3FromJson(Map<String, dynamic> json) => ByChannel3(
  channel: json['channel'] as String,
  orders: (json['orders'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByChannel3ToJson(ByChannel3 instance) =>
    <String, dynamic>{
      'channel': instance.channel,
      'orders': instance.orders,
      'amount': instance.amount,
    };
