// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'hours.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Hours _$HoursFromJson(Map<String, dynamic> json) => Hours(
  hour: (json['hour'] as num).toInt(),
  count: (json['count'] as num).toInt(),
  medianSeconds: (json['median_seconds'] as num?)?.toInt(),
  p90Seconds: (json['p90_seconds'] as num?)?.toInt(),
  withTarget: (json['with_target'] as num).toInt(),
  overTarget: (json['over_target'] as num).toInt(),
  overTargetShare: json['over_target_share'] as String?,
);

Map<String, dynamic> _$HoursToJson(Hours instance) => <String, dynamic>{
  'hour': instance.hour,
  'count': instance.count,
  'median_seconds': instance.medianSeconds,
  'p90_seconds': instance.p90Seconds,
  'with_target': instance.withTarget,
  'over_target': instance.overTarget,
  'over_target_share': instance.overTargetShare,
};
