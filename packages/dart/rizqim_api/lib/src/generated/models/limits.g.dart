// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'limits.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Limits _$LimitsFromJson(Map<String, dynamic> json) => Limits(
  min: (json['min'] as num).toInt(),
  max: (json['max'] as num).toInt(),
);

Map<String, dynamic> _$LimitsToJson(Limits instance) => <String, dynamic>{
  'min': instance.min,
  'max': instance.max,
};
