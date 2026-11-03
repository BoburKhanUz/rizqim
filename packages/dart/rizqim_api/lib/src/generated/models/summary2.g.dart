// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'summary2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Summary2 _$Summary2FromJson(Map<String, dynamic> json) => Summary2(
  count: (json['count'] as num).toInt(),
  medianSeconds: (json['median_seconds'] as num?)?.toInt(),
  p90Seconds: (json['p90_seconds'] as num?)?.toInt(),
  withTarget: (json['with_target'] as num).toInt(),
  overTarget: (json['over_target'] as num).toInt(),
  overTargetShare: json['over_target_share'] as String?,
);

Map<String, dynamic> _$Summary2ToJson(Summary2 instance) => <String, dynamic>{
  'count': instance.count,
  'median_seconds': instance.medianSeconds,
  'p90_seconds': instance.p90Seconds,
  'with_target': instance.withTarget,
  'over_target': instance.overTarget,
  'over_target_share': instance.overTargetShare,
};
