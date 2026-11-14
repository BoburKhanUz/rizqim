// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data263.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data263 _$Data263FromJson(Map<String, dynamic> json) => Data263(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data263ToJson(Data263 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
