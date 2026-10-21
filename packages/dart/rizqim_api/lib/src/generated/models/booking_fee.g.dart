// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'booking_fee.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BookingFee _$BookingFeeFromJson(Map<String, dynamic> json) => BookingFee(
  name: json['name'] as String,
  mxik: json['mxik'] as String?,
  packageCode: json['package_code'] as String?,
  unit: json['unit'] as String,
);

Map<String, dynamic> _$BookingFeeToJson(BookingFee instance) =>
    <String, dynamic>{
      'name': instance.name,
      'mxik': instance.mxik,
      'package_code': instance.packageCode,
      'unit': instance.unit,
    };
