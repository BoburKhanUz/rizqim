// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'shift.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Shift _$ShiftFromJson(Map<String, dynamic> json) => Shift(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  businessDate: json['business_date'] as String,
  openedAt: json['opened_at'] as String,
  openedBy: (json['opened_by'] as num?)?.toInt(),
  openingCash: json['opening_cash'] as String,
  deviceId: json['device_id'] as String,
);

Map<String, dynamic> _$ShiftToJson(Shift instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'status': instance.status,
  'business_date': instance.businessDate,
  'opened_at': instance.openedAt,
  'opened_by': instance.openedBy,
  'opening_cash': instance.openingCash,
  'device_id': instance.deviceId,
};
