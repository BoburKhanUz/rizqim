// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data362.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data362 _$Data362FromJson(Map<String, dynamic> json) => Data362(
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

Map<String, dynamic> _$Data362ToJson(Data362 instance) => <String, dynamic>{
  'status': instance.status,
  'live': instance.live,
  'heartbeat_seconds': instance.heartbeatSeconds,
  'silence_seconds': instance.silenceSeconds,
  'poll_seconds': instance.pollSeconds,
  'reverb': instance.reverb,
};
