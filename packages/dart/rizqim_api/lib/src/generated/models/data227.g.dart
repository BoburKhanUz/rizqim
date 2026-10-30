// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data227.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data227 _$Data227FromJson(Map<String, dynamic> json) => Data227(
  orderUuid: json['order_uuid'] as String,
  number: (json['number'] as num).toInt(),
  businessDate: json['business_date'] as String?,
  status: json['status'] == null
      ? null
      : PickupStatus.fromJson(json['status'] as String),
  channel: json['channel'] as String,
  orderStatus: json['order_status'] as String,
  readyAt: json['ready_at'] as String?,
  calledAt: json['called_at'] as String?,
  callCount: (json['call_count'] as num).toInt(),
  handedAt: json['handed_at'] as String?,
  openedAt: json['opened_at'] as String?,
);

Map<String, dynamic> _$Data227ToJson(Data227 instance) => <String, dynamic>{
  'order_uuid': instance.orderUuid,
  'number': instance.number,
  'business_date': instance.businessDate,
  'status': instance.status,
  'channel': instance.channel,
  'order_status': instance.orderStatus,
  'ready_at': instance.readyAt,
  'called_at': instance.calledAt,
  'call_count': instance.callCount,
  'handed_at': instance.handedAt,
  'opened_at': instance.openedAt,
};
