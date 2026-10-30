// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'open_shifts7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

OpenShifts7 _$OpenShifts7FromJson(Map<String, dynamic> json) => OpenShifts7(
  uuid: json['uuid'] as String,
  deviceName: json['device_name'] as String?,
  businessDate: json['business_date'] as String,
  openedAt: json['opened_at'] as String,
  expectedCash: json['expected_cash'] as String,
);

Map<String, dynamic> _$OpenShifts7ToJson(OpenShifts7 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'device_name': instance.deviceName,
      'business_date': instance.businessDate,
      'opened_at': instance.openedAt,
      'expected_cash': instance.expectedCash,
    };
