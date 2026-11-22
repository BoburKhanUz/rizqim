// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data367.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data367 _$Data367FromJson(Map<String, dynamic> json) => Data367(
  status: json['status'] as String,
  live: json['live'] as bool,
  heartbeatSeconds: (json['heartbeat_seconds'] as num).toInt(),
  silenceSeconds: (json['silence_seconds'] as num).toInt(),
  pollSeconds: PollSeconds.fromJson(
    json['poll_seconds'] as Map<String, dynamic>,
  ),
  reverb: json['reverb'] == null
      ? null
      : Reverb.fromJson(json['reverb'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data367ToJson(Data367 instance) => <String, dynamic>{
  'status': instance.status,
  'live': instance.live,
  'heartbeat_seconds': instance.heartbeatSeconds,
  'silence_seconds': instance.silenceSeconds,
  'poll_seconds': instance.pollSeconds,
  'reverb': instance.reverb,
};
