// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'guests5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Guests5 _$Guests5FromJson(Map<String, dynamic> json) => Guests5(
  guaranteed: (json['guaranteed'] as num).toInt(),
  minimum: (json['minimum'] as num).toInt(),
  actual: (json['actual'] as num?)?.toInt(),
  billed: (json['billed'] as num).toInt(),
);

Map<String, dynamic> _$Guests5ToJson(Guests5 instance) => <String, dynamic>{
  'guaranteed': instance.guaranteed,
  'minimum': instance.minimum,
  'actual': instance.actual,
  'billed': instance.billed,
};
