// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_channel7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByChannel7 _$ByChannel7FromJson(Map<String, dynamic> json) => ByChannel7(
  channel: json['channel'] as String,
  orders: (json['orders'] as num).toInt(),
  amount: json['amount'] as String,
);

Map<String, dynamic> _$ByChannel7ToJson(ByChannel7 instance) =>
    <String, dynamic>{
      'channel': instance.channel,
      'orders': instance.orders,
      'amount': instance.amount,
    };
