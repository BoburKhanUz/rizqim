// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'service_charge3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ServiceCharge3 _$ServiceCharge3FromJson(Map<String, dynamic> json) =>
    ServiceCharge3(
      name: json['name'] as String?,
      mxik: json['mxik'] as String?,
      packageCode: json['package_code'] as String?,
      unit: json['unit'] as String?,
    );

Map<String, dynamic> _$ServiceCharge3ToJson(ServiceCharge3 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'mxik': instance.mxik,
      'package_code': instance.packageCode,
      'unit': instance.unit,
    };
