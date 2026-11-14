// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'settlement5.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Settlement5 _$Settlement5FromJson(Map<String, dynamic> json) => Settlement5(
  uuid: json['uuid'] as String,
  status: json['status'] as String,
  billUuid: json['bill_uuid'] as String?,
  appliedAmount: json['applied_amount'] as String,
  excessAmount: json['excess_amount'] as String,
  excessStatus: json['excess_status'] as String,
  settledAt: json['settled_at'] as String?,
);

Map<String, dynamic> _$Settlement5ToJson(Settlement5 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'status': instance.status,
      'bill_uuid': instance.billUuid,
      'applied_amount': instance.appliedAmount,
      'excess_amount': instance.excessAmount,
      'excess_status': instance.excessStatus,
      'settled_at': instance.settledAt,
    };
