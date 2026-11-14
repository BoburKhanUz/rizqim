// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cancellation_rules.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

CancellationRules _$CancellationRulesFromJson(Map<String, dynamic> json) =>
    CancellationRules(
      minDays: (json['min_days'] as num).toInt(),
      retainedPercent: json['retained_percent'] as String,
    );

Map<String, dynamic> _$CancellationRulesToJson(CancellationRules instance) =>
    <String, dynamic>{
      'min_days': instance.minDays,
      'retained_percent': instance.retainedPercent,
    };
