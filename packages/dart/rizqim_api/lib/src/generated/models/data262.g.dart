// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data262.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data262 _$Data262FromJson(Map<String, dynamic> json) => Data262(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data262ToJson(Data262 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
