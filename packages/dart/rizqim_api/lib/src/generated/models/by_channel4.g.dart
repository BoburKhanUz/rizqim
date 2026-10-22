// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_channel4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByChannel4 _$ByChannel4FromJson(Map<String, dynamic> json) => ByChannel4(
  channel: json['channel'] as String,
  orders: (json['orders'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByChannel4ToJson(ByChannel4 instance) =>
    <String, dynamic>{
      'channel': instance.channel,
      'orders': instance.orders,
      'amount': instance.amount,
    };
