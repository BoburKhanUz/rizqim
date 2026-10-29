// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'open_shifts5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

OpenShifts5 _$OpenShifts5FromJson(Map<String, dynamic> json) => OpenShifts5(
  uuid: json['uuid'] as String,
  deviceName: json['device_name'] as String?,
  businessDate: json['business_date'] as String,
  openedAt: json['opened_at'] as String,
  expectedCash: json['expected_cash'] as String,
);

Map<String, dynamic> _$OpenShifts5ToJson(OpenShifts5 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'device_name': instance.deviceName,
      'business_date': instance.businessDate,
      'opened_at': instance.openedAt,
      'expected_cash': instance.expectedCash,
    };
