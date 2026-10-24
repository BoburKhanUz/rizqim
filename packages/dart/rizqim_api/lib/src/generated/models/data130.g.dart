// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data130.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data130 _$Data130FromJson(Map<String, dynamic> json) => Data130(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data130ToJson(Data130 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
