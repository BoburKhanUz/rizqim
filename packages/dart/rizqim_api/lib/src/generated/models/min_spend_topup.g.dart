// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'min_spend_topup.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

MinSpendTopup _$MinSpendTopupFromJson(Map<String, dynamic> json) =>
    MinSpendTopup(
      name: json['name'] as String,
      mxik: json['mxik'] as String?,
      packageCode: json['package_code'] as String?,
      unit: json['unit'] as String,
    );

Map<String, dynamic> _$MinSpendTopupToJson(MinSpendTopup instance) =>
    <String, dynamic>{
      'name': instance.name,
      'mxik': instance.mxik,
      'package_code': instance.packageCode,
      'unit': instance.unit,
    };
