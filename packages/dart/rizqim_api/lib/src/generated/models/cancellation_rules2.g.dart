// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cancellation_rules2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CancellationRules2 _$CancellationRules2FromJson(Map<String, dynamic> json) =>
    CancellationRules2(
      minDays: (json['min_days'] as num).toInt(),
      retainedPercent: json['retained_percent'] as String,
    );

Map<String, dynamic> _$CancellationRules2ToJson(CancellationRules2 instance) =>
    <String, dynamic>{
      'min_days': instance.minDays,
      'retained_percent': instance.retainedPercent,
    };
