// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data195.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data195 _$Data195FromJson(Map<String, dynamic> json) => Data195(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data195ToJson(Data195 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
