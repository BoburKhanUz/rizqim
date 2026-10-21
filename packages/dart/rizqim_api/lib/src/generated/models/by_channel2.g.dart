// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'by_channel2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ByChannel2 _$ByChannel2FromJson(Map<String, dynamic> json) => ByChannel2(
  channel: json['channel'] as String,
  label: json['label'] as String,
  orders: (json['orders'] as num).toInt(),
  revenue: json['revenue'] as String,
);

Map<String, dynamic> _$ByChannel2ToJson(ByChannel2 instance) =>
    <String, dynamic>{
      'channel': instance.channel,
      'label': instance.label,
      'orders': instance.orders,
      'revenue': instance.revenue,
    };
