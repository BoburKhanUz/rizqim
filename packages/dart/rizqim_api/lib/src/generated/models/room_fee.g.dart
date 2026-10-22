// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'room_fee.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

RoomFee _$RoomFeeFromJson(Map<String, dynamic> json) => RoomFee(
  name: json['name'] as String,
  mxik: json['mxik'] as String?,
  packageCode: json['package_code'] as String?,
  unit: json['unit'] as String,
);

Map<String, dynamic> _$RoomFeeToJson(RoomFee instance) => <String, dynamic>{
  'name': instance.name,
  'mxik': instance.mxik,
  'package_code': instance.packageCode,
  'unit': instance.unit,
};
