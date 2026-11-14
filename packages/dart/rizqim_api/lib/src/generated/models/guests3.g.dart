// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'guests3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Guests3 _$Guests3FromJson(Map<String, dynamic> json) => Guests3(
  guaranteed: (json['guaranteed'] as num).toInt(),
  minimum: (json['minimum'] as num).toInt(),
  actual: (json['actual'] as num?)?.toInt(),
  billed: (json['billed'] as num).toInt(),
);

Map<String, dynamic> _$Guests3ToJson(Guests3 instance) => <String, dynamic>{
  'guaranteed': instance.guaranteed,
  'minimum': instance.minimum,
  'actual': instance.actual,
  'billed': instance.billed,
};
