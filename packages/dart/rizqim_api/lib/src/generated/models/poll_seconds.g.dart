// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'poll_seconds.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PollSeconds _$PollSecondsFromJson(Map<String, dynamic> json) => PollSeconds(
  live: (json['live'] as num).toInt(),
  fallback: (json['fallback'] as num).toInt(),
);

Map<String, dynamic> _$PollSecondsToJson(PollSeconds instance) =>
    <String, dynamic>{'live': instance.live, 'fallback': instance.fallback};
