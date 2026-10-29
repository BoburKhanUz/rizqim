// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data81.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data81 _$Data81FromJson(Map<String, dynamic> json) => Data81(
  tin: json['tin'] as String?,
  taxRegime: json['tax_regime'] == null
      ? null
      : TaxRegime.fromJson(json['tax_regime'] as String?),
  fiscalSettings: FiscalSettings.fromJson(
    json['fiscal_settings'] as Map<String, dynamic>,
  ),
  drivers: (json['drivers'] as List<dynamic>).map((e) => e as String).toList(),
  taxRegimes: (json['tax_regimes'] as List<dynamic>)
      .map((e) => TaxRegimes.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data81ToJson(Data81 instance) => <String, dynamic>{
  'tin': instance.tin,
  'tax_regime': instance.taxRegime,
  'fiscal_settings': instance.fiscalSettings,
  'drivers': instance.drivers,
  'tax_regimes': instance.taxRegimes,
};
