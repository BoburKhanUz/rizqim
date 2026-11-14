// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'channels3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Channels3 _$Channels3FromJson(Map<String, dynamic> json) => Channels3(
  channel: Channel3.fromJson(json['channel'] as String),
  isHidden: json['is_hidden'] as bool?,
  pricePercent: json['price_percent'] as num?,
);

Map<String, dynamic> _$Channels3ToJson(Channels3 instance) => <String, dynamic>{
  'channel': instance.channel,
  'is_hidden': instance.isHidden,
  'price_percent': instance.pricePercent,
};
