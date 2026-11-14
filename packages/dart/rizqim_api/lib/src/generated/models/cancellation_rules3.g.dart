// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cancellation_rules3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CancellationRules3 _$CancellationRules3FromJson(Map<String, dynamic> json) =>
    CancellationRules3(
      minDays: (json['min_days'] as num).toInt(),
      retainedPercent: json['retained_percent'] as num,
    );

Map<String, dynamic> _$CancellationRules3ToJson(CancellationRules3 instance) =>
    <String, dynamic>{
      'min_days': instance.minDays,
      'retained_percent': instance.retainedPercent,
    };
