// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'deposit.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Deposit _$DepositFromJson(Map<String, dynamic> json) => Deposit(
  name: json['name'] as String,
  mxik: json['mxik'] as String?,
  packageCode: json['package_code'] as String?,
  unit: json['unit'] as String,
);

Map<String, dynamic> _$DepositToJson(Deposit instance) => <String, dynamic>{
  'name': instance.name,
  'mxik': instance.mxik,
  'package_code': instance.packageCode,
  'unit': instance.unit,
};
