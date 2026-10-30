// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'expo.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Expo _$ExpoFromJson(Map<String, dynamic> json) => Expo(
  stationId: (json['station_id'] as num).toInt(),
  name: json['name'] as String,
  targetSeconds: (json['target_seconds'] as num?)?.toInt(),
  count: (json['count'] as num).toInt(),
  medianSeconds: (json['median_seconds'] as num?)?.toInt(),
  p90Seconds: (json['p90_seconds'] as num?)?.toInt(),
  withTarget: (json['with_target'] as num).toInt(),
  overTarget: (json['over_target'] as num).toInt(),
  overTargetShare: json['over_target_share'] as String?,
);

Map<String, dynamic> _$ExpoToJson(Expo instance) => <String, dynamic>{
  'station_id': instance.stationId,
  'name': instance.name,
  'target_seconds': instance.targetSeconds,
  'count': instance.count,
  'median_seconds': instance.medianSeconds,
  'p90_seconds': instance.p90Seconds,
  'with_target': instance.withTarget,
  'over_target': instance.overTarget,
  'over_target_share': instance.overTargetShare,
};
