// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data151.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data151 _$Data151FromJson(Map<String, dynamic> json) => Data151(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data151ToJson(Data151 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
