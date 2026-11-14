// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'settlement3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Settlement3 _$Settlement3FromJson(Map<String, dynamic> json) => Settlement3(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  billUuid: json['bill_uuid'] as String?,
  appliedAmount: json['applied_amount'] as String,
  excessAmount: json['excess_amount'] as String,
  excessStatus: json['excess_status'] as String,
  settledAt: json['settled_at'] as String?,
);

Map<String, dynamic> _$Settlement3ToJson(Settlement3 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'status': instance.status,
      'bill_uuid': instance.billUuid,
      'applied_amount': instance.appliedAmount,
      'excess_amount': instance.excessAmount,
      'excess_status': instance.excessStatus,
      'settled_at': instance.settledAt,
    };
