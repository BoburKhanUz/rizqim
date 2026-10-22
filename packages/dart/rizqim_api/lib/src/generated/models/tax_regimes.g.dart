// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tax_regimes.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

TaxRegimes _$TaxRegimesFromJson(Map<String, dynamic> json) => TaxRegimes(
  value: json['value'] as String,
  label: json['label'] as String,
  vatRate: json['vat_rate'] as String,
);

Map<String, dynamic> _$TaxRegimesToJson(TaxRegimes instance) =>
    <String, dynamic>{
      'value': instance.value,
      'label': instance.label,
      'vat_rate': instance.vatRate,
    };
