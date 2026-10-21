// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'credit.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Credit _$CreditFromJson(Map<String, dynamic> json) => Credit(
  name: json['name'] as String,
  mxik: json['mxik'] as String?,
  packageCode: json['package_code'] as String?,
  unit: json['unit'] as String,
);

Map<String, dynamic> _$CreditToJson(Credit instance) => <String, dynamic>{
  'name': instance.name,
  'mxik': instance.mxik,
  'package_code': instance.packageCode,
  'unit': instance.unit,
};
