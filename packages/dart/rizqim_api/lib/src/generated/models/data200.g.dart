// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data200.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data200 _$Data200FromJson(Map<String, dynamic> json) => Data200(
  channel: Channel4.fromJson(json['channel'] as String),
  queued: json['queued'] as bool,
);

Map<String, dynamic> _$Data200ToJson(Data200 instance) => <String, dynamic>{
  'channel': instance.channel,
  'queued': instance.queued,
};
