// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'history4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

History4 _$History4FromJson(Map<String, dynamic> json) => History4(
  source: json['source'] as String,
  bankTotal: json['bank_total'] as String,
  bankCount: (json['bank_count'] as num?)?.toInt(),
  difference: json['difference'] as String,
  overThreshold: json['over_threshold'] as bool,
  enteredBy: (json['entered_by'] as num?)?.toInt(),
  enteredAt: json['entered_at'] as String?,
);

Map<String, dynamic> _$History4ToJson(History4 instance) => <String, dynamic>{
  'source': instance.source,
  'bank_total': instance.bankTotal,
  'bank_count': instance.bankCount,
  'difference': instance.difference,
  'over_threshold': instance.overThreshold,
  'entered_by': instance.enteredBy,
  'entered_at': instance.enteredAt,
};
