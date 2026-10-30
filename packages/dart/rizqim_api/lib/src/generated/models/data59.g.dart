// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data59.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data59 _$Data59FromJson(Map<String, dynamic> json) => Data59(
  combos: (json['combos'] as List<dynamic>)
      .map((e) => Combos.fromJson(e as Map<String, dynamic>))
      .toList(),
  fiscalMode: ComboFiscalMode.fromJson(json['fiscal_mode'] as String),
);

Map<String, dynamic> _$Data59ToJson(Data59 instance) => <String, dynamic>{
  'combos': instance.combos,
  'fiscal_mode': instance.fiscalMode,
};
