// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data270.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data270 _$Data270FromJson(Map<String, dynamic> json) => Data270(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data270ToJson(Data270 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
