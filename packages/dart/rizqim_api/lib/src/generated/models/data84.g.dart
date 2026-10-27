// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data84.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data84 _$Data84FromJson(Map<String, dynamic> json) => Data84(
  enabled: json['enabled'] as bool,
  tinSet: json['tin_set'] as bool,
  taxRegime: json['tax_regime'] as String?,
  productsMissingMxik: (json['products_missing_mxik'] as List<dynamic>)
      .map((e) => ProductsMissingMxik.fromJson(e as Map<String, dynamic>))
      .toList(),
  chargesMissingMxik: (json['charges_missing_mxik'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
);

Map<String, dynamic> _$Data84ToJson(Data84 instance) => <String, dynamic>{
  'enabled': instance.enabled,
  'tin_set': instance.tinSet,
  'tax_regime': instance.taxRegime,
  'products_missing_mxik': instance.productsMissingMxik,
  'charges_missing_mxik': instance.chargesMissingMxik,
};
