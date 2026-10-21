// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'delivery.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Delivery _$DeliveryFromJson(Map<String, dynamic> json) => Delivery(
  name: json['name'] as String,
  mxik: json['mxik'] as String?,
  packageCode: json['package_code'] as String?,
  unit: json['unit'] as String,
);

Map<String, dynamic> _$DeliveryToJson(Delivery instance) => <String, dynamic>{
  'name': instance.name,
  'mxik': instance.mxik,
  'package_code': instance.packageCode,
  'unit': instance.unit,
};
