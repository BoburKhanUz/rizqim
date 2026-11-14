// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data62.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data62 _$Data62FromJson(Map<String, dynamic> json) => Data62(
  combos: (json['combos'] as List<dynamic>)
      .map((e) => Combos.fromJson(e as Map<String, dynamic>))
      .toList(),
  fiscalMode: ComboFiscalMode.fromJson(json['fiscal_mode'] as String),
);

Map<String, dynamic> _$Data62ToJson(Data62 instance) => <String, dynamic>{
  'combos': instance.combos,
  'fiscal_mode': instance.fiscalMode,
};
