// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data266.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data266 _$Data266FromJson(Map<String, dynamic> json) => Data266(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data266ToJson(Data266 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
