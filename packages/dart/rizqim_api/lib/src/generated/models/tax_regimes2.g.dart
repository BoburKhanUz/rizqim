// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tax_regimes2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

TaxRegimes2 _$TaxRegimes2FromJson(Map<String, dynamic> json) => TaxRegimes2(
  value: json['value'] as String,
  label: json['label'] as String,
  vatRate: json['vat_rate'] as String,
);

Map<String, dynamic> _$TaxRegimes2ToJson(TaxRegimes2 instance) =>
    <String, dynamic>{
      'value': instance.value,
      'label': instance.label,
      'vat_rate': instance.vatRate,
    };
