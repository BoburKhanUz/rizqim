// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data352.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data352 _$Data352FromJson(Map<String, dynamic> json) => Data352(
  serverTime: json['server_time'] as String,
  clockSkewSeconds: (json['clock_skew_seconds'] as num?)?.toInt(),
  clockWarning: json['clock_warning'] as bool,
  stoppedAt: (json['stopped_at'] as num?)?.toInt(),
  results: (json['results'] as List<dynamic>)
      .map((e) => Results2.fromJson(e as Map<String, dynamic>))
      .toList(),
  unreadable: (json['unreadable'] as List<dynamic>)
      .map((e) => Unreadable.fromJson(e as Map<String, dynamic>))
      .toList(),
  device: Device21.fromJson(json['device'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data352ToJson(Data352 instance) => <String, dynamic>{
  'server_time': instance.serverTime,
  'clock_skew_seconds': instance.clockSkewSeconds,
  'clock_warning': instance.clockWarning,
  'stopped_at': instance.stoppedAt,
  'results': instance.results,
  'unreadable': instance.unreadable,
  'device': instance.device,
};
