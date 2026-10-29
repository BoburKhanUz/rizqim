// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data150.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data150 _$Data150FromJson(Map<String, dynamic> json) => Data150(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data150ToJson(Data150 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
