// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'combo_parts.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ComboParts _$ComboPartsFromJson(Map<String, dynamic> json) => ComboParts(
  name: json['name'] as String,
  inCombos: json['in_combos'] as num,
  alone: json['alone'] as num,
  total: json['total'] as num,
);

Map<String, dynamic> _$ComboPartsToJson(ComboParts instance) =>
    <String, dynamic>{
      'name': instance.name,
      'in_combos': instance.inCombos,
      'alone': instance.alone,
      'total': instance.total,
    };
