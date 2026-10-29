// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'history2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

History2 _$History2FromJson(Map<String, dynamic> json) => History2(
  source: json['source'] as String,
  bankTotal: json['bank_total'] as String,
  bankCount: (json['bank_count'] as num?)?.toInt(),
  difference: json['difference'] as String,
  overThreshold: json['over_threshold'] as bool,
  enteredBy: (json['entered_by'] as num?)?.toInt(),
  enteredAt: json['entered_at'] as String?,
);

Map<String, dynamic> _$History2ToJson(History2 instance) => <String, dynamic>{
  'source': instance.source,
  'bank_total': instance.bankTotal,
  'bank_count': instance.bankCount,
  'difference': instance.difference,
  'over_threshold': instance.overThreshold,
  'entered_by': instance.enteredBy,
  'entered_at': instance.enteredAt,
};
