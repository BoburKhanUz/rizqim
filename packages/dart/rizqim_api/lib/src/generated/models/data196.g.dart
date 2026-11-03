// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data196.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data196 _$Data196FromJson(Map<String, dynamic> json) => Data196(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data196ToJson(Data196 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
