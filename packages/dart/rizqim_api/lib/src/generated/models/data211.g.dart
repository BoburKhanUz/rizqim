// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data211.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data211 _$Data211FromJson(Map<String, dynamic> json) => Data211(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data211ToJson(Data211 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
