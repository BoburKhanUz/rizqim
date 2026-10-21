// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'min_spend_topup2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MinSpendTopup2 _$MinSpendTopup2FromJson(Map<String, dynamic> json) =>
    MinSpendTopup2(
      name: json['name'] as String,
      mxik: json['mxik'] as String?,
      packageCode: json['package_code'] as String?,
      unit: json['unit'] as String,
    );

Map<String, dynamic> _$MinSpendTopup2ToJson(MinSpendTopup2 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'mxik': instance.mxik,
      'package_code': instance.packageCode,
      'unit': instance.unit,
    };
