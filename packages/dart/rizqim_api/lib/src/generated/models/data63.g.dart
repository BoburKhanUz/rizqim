// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data63.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data63 _$Data63FromJson(Map<String, dynamic> json) => Data63(
  combos: (json['combos'] as List<dynamic>)
      .map((e) => Combos.fromJson(e as Map<String, dynamic>))
      .toList(),
  fiscalMode: ComboFiscalMode.fromJson(json['fiscal_mode'] as String),
);

Map<String, dynamic> _$Data63ToJson(Data63 instance) => <String, dynamic>{
  'combos': instance.combos,
  'fiscal_mode': instance.fiscalMode,
};
