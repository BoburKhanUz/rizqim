// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data265.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data265 _$Data265FromJson(Map<String, dynamic> json) => Data265(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data265ToJson(Data265 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
