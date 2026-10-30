// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data244.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data244 _$Data244FromJson(Map<String, dynamic> json) => Data244(
  serverTime: json['server_time'] as String,
  clockSkewSeconds: (json['clock_skew_seconds'] as num?)?.toInt(),
  clockWarning: json['clock_warning'] as bool,
  stoppedAt: (json['stopped_at'] as num?)?.toInt(),
  results: (json['results'] as List<dynamic>)
      .map((e) => Results.fromJson(e as Map<String, dynamic>))
      .toList(),
  device: Device20.fromJson(json['device'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data244ToJson(Data244 instance) => <String, dynamic>{
  'server_time': instance.serverTime,
  'clock_skew_seconds': instance.clockSkewSeconds,
  'clock_warning': instance.clockWarning,
  'stopped_at': instance.stoppedAt,
  'results': instance.results,
  'device': instance.device,
};
