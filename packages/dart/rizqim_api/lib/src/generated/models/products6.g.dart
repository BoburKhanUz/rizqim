// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'products6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Products6 _$Products6FromJson(Map<String, dynamic> json) => Products6(
  productId: (json['product_id'] as num?)?.toInt(),
  name: json['name'] as String,
  count: (json['count'] as num).toInt(),
  medianSeconds: (json['median_seconds'] as num?)?.toInt(),
  p90Seconds: (json['p90_seconds'] as num?)?.toInt(),
  withTarget: (json['with_target'] as num).toInt(),
  overTarget: (json['over_target'] as num).toInt(),
  overTargetShare: json['over_target_share'] as String?,
);

Map<String, dynamic> _$Products6ToJson(Products6 instance) => <String, dynamic>{
  'product_id': instance.productId,
  'name': instance.name,
  'count': instance.count,
  'median_seconds': instance.medianSeconds,
  'p90_seconds': instance.p90Seconds,
  'with_target': instance.withTarget,
  'over_target': instance.overTarget,
  'over_target_share': instance.overTargetShare,
};
