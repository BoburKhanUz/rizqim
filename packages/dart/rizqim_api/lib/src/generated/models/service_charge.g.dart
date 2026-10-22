// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'service_charge.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ServiceCharge _$ServiceChargeFromJson(Map<String, dynamic> json) =>
    ServiceCharge(
      name: json['name'] as String,
      mxik: json['mxik'] as String?,
      packageCode: json['package_code'] as String?,
      unit: json['unit'] as String,
    );

Map<String, dynamic> _$ServiceChargeToJson(ServiceCharge instance) =>
    <String, dynamic>{
      'name': instance.name,
      'mxik': instance.mxik,
      'package_code': instance.packageCode,
      'unit': instance.unit,
    };
