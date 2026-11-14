// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data138.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data138 _$Data138FromJson(Map<String, dynamic> json) => Data138(
  reservePercent: json['reserve_percent'] as String,
  osh: Osh.fromJson(json['osh'] as Map<String, dynamic>),
  portionWeights: (json['portion_weights'] as List<dynamic>)
      .map((e) => PortionWeights.fromJson(e as Map<String, dynamic>))
      .toList(),
  mxik: Mxik.fromJson(json['mxik'] as Map<String, dynamic>),
  contractTemplate: json['contract_template'] as String,
  contractTemplateIsDefault: json['contract_template_is_default'] as bool,
  contractPlaceholders: (json['contract_placeholders'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  version: (json['version'] as num).toInt(),
);

Map<String, dynamic> _$Data138ToJson(Data138 instance) => <String, dynamic>{
  'reserve_percent': instance.reservePercent,
  'osh': instance.osh,
  'portion_weights': instance.portionWeights,
  'mxik': instance.mxik,
  'contract_template': instance.contractTemplate,
  'contract_template_is_default': instance.contractTemplateIsDefault,
  'contract_placeholders': instance.contractPlaceholders,
  'version': instance.version,
};
