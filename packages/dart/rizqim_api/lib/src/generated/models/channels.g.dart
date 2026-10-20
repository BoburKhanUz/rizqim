// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'channels.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Channels _$ChannelsFromJson(Map<String, dynamic> json) => Channels(
  channel: json['channel'] as String,
  label: json['label'] as String,
  enabled: json['enabled'] as bool,
  available: json['available'] as bool,
);

Map<String, dynamic> _$ChannelsToJson(Channels instance) => <String, dynamic>{
  'channel': instance.channel,
  'label': instance.label,
  'enabled': instance.enabled,
  'available': instance.available,
};
