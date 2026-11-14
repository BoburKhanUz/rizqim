// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'guests2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Guests2 _$Guests2FromJson(Map<String, dynamic> json) => Guests2(
  guaranteed: (json['guaranteed'] as num).toInt(),
  minimum: (json['minimum'] as num).toInt(),
  actual: (json['actual'] as num?)?.toInt(),
  baseValue: (json['base'] as num).toInt(),
  reservePercent: json['reserve_percent'] as String,
  planned: (json['planned'] as num).toInt(),
);

Map<String, dynamic> _$Guests2ToJson(Guests2 instance) => <String, dynamic>{
  'guaranteed': instance.guaranteed,
  'minimum': instance.minimum,
  'actual': instance.actual,
  'base': instance.baseValue,
  'reserve_percent': instance.reservePercent,
  'planned': instance.planned,
};
