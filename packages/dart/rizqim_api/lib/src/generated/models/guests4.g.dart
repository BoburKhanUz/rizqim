// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'guests4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Guests4 _$Guests4FromJson(Map<String, dynamic> json) => Guests4(
  guaranteed: (json['guaranteed'] as num).toInt(),
  minimum: (json['minimum'] as num).toInt(),
  actual: (json['actual'] as num?)?.toInt(),
  billed: (json['billed'] as num).toInt(),
);

Map<String, dynamic> _$Guests4ToJson(Guests4 instance) => <String, dynamic>{
  'guaranteed': instance.guaranteed,
  'minimum': instance.minimum,
  'actual': instance.actual,
  'billed': instance.billed,
};
