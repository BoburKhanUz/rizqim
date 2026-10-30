// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data179.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data179 _$Data179FromJson(Map<String, dynamic> json) => Data179(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data179ToJson(Data179 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
