// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data202.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data202 _$Data202FromJson(Map<String, dynamic> json) => Data202(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data202ToJson(Data202 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
