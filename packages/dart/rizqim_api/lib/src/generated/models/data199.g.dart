// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data199.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data199 _$Data199FromJson(Map<String, dynamic> json) => Data199(
  serverTime: json['server_time'] as String,
  clockSkewSeconds: (json['clock_skew_seconds'] as num?)?.toInt(),
  clockWarning: json['clock_warning'] as bool,
  stoppedAt: (json['stopped_at'] as num?)?.toInt(),
  results: (json['results'] as List<dynamic>)
      .map((e) => Results.fromJson(e as Map<String, dynamic>))
      .toList(),
  device: Device20.fromJson(json['device'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data199ToJson(Data199 instance) => <String, dynamic>{
  'server_time': instance.serverTime,
  'clock_skew_seconds': instance.clockSkewSeconds,
  'clock_warning': instance.clockWarning,
  'stopped_at': instance.stoppedAt,
  'results': instance.results,
  'device': instance.device,
};
