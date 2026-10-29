// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'queue16.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Queue16 _$Queue16FromJson(Map<String, dynamic> json) => Queue16(
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  status: json['status'] == null
      ? null
      : PickupStatus.fromJson(json['status'] as String),
  readyAt: json['ready_at'] as String?,
  calledAt: json['called_at'] as String?,
  callCount: (json['call_count'] as num).toInt(),
  handedAt: json['handed_at'] as String?,
);

Map<String, dynamic> _$Queue16ToJson(Queue16 instance) => <String, dynamic>{
  'number': instance.number,
  'business_date': instance.businessDate,
  'status': instance.status,
  'ready_at': instance.readyAt,
  'called_at': instance.calledAt,
  'call_count': instance.callCount,
  'handed_at': instance.handedAt,
};
