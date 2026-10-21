// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'service_charge2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ServiceCharge2 _$ServiceCharge2FromJson(Map<String, dynamic> json) =>
    ServiceCharge2(
      name: json['name'] as String,
      mxik: json['mxik'] as String?,
      packageCode: json['package_code'] as String?,
      unit: json['unit'] as String,
    );

Map<String, dynamic> _$ServiceCharge2ToJson(ServiceCharge2 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'mxik': instance.mxik,
      'package_code': instance.packageCode,
      'unit': instance.unit,
    };
