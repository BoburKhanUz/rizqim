// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_channel.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByChannel _$ByChannelFromJson(Map<String, dynamic> json) => ByChannel(
  channel: json['channel'] as String,
  orders: (json['orders'] as num).toInt(),
  revenue: json['revenue'] as num,
);

Map<String, dynamic> _$ByChannelToJson(ByChannel instance) => <String, dynamic>{
  'channel': instance.channel,
  'orders': instance.orders,
  'revenue': instance.revenue,
};
