// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'channels2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Channels2 _$Channels2FromJson(Map<String, dynamic> json) => Channels2(
  channel: json['channel'] as String,
  label: json['label'] as String,
  enabled: json['enabled'] as bool,
  available: json['available'] as bool,
);

Map<String, dynamic> _$Channels2ToJson(Channels2 instance) => <String, dynamic>{
  'channel': instance.channel,
  'label': instance.label,
  'enabled': instance.enabled,
  'available': instance.available,
};
