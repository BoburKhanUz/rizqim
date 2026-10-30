// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'stations7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Stations7 _$Stations7FromJson(Map<String, dynamic> json) => Stations7(
  stationId: (json['station_id'] as num?)?.toInt(),
  name: json['name'] as String,
  kind: json['kind'] as String?,
  targetSeconds: (json['target_seconds'] as num?)?.toInt(),
  count: (json['count'] as num).toInt(),
  medianSeconds: (json['median_seconds'] as num?)?.toInt(),
  p90Seconds: (json['p90_seconds'] as num?)?.toInt(),
  withTarget: (json['with_target'] as num).toInt(),
  overTarget: (json['over_target'] as num).toInt(),
  overTargetShare: json['over_target_share'] as String?,
);

Map<String, dynamic> _$Stations7ToJson(Stations7 instance) => <String, dynamic>{
  'station_id': instance.stationId,
  'name': instance.name,
  'kind': instance.kind,
  'target_seconds': instance.targetSeconds,
  'count': instance.count,
  'median_seconds': instance.medianSeconds,
  'p90_seconds': instance.p90Seconds,
  'with_target': instance.withTarget,
  'over_target': instance.overTarget,
  'over_target_share': instance.overTargetShare,
};
