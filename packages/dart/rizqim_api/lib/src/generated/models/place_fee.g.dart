// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'place_fee.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PlaceFee _$PlaceFeeFromJson(Map<String, dynamic> json) => PlaceFee(
  name: json['name'] as String,
  mxik: json['mxik'] as String?,
  packageCode: json['package_code'] as String?,
  unit: json['unit'] as String,
);

Map<String, dynamic> _$PlaceFeeToJson(PlaceFee instance) => <String, dynamic>{
  'name': instance.name,
  'mxik': instance.mxik,
  'package_code': instance.packageCode,
  'unit': instance.unit,
};
