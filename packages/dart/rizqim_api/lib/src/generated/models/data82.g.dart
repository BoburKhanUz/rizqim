// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data82.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data82 _$Data82FromJson(Map<String, dynamic> json) => Data82(
  tin: json['tin'] as String?,
  taxRegime: json['tax_regime'] == null
      ? null
      : TaxRegime.fromJson(json['tax_regime'] as String?),
  fiscalSettings: FiscalSettings2.fromJson(
    json['fiscal_settings'] as Map<String, dynamic>,
  ),
  drivers: (json['drivers'] as List<dynamic>).map((e) => e as String).toList(),
  taxRegimes: (json['tax_regimes'] as List<dynamic>)
      .map((e) => TaxRegimes2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data82ToJson(Data82 instance) => <String, dynamic>{
  'tin': instance.tin,
  'tax_regime': instance.taxRegime,
  'fiscal_settings': instance.fiscalSettings,
  'drivers': instance.drivers,
  'tax_regimes': instance.taxRegimes,
};
