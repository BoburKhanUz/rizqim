// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data258.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data258 _$Data258FromJson(Map<String, dynamic> json) => Data258(
  uuid: json['uuid'] as String,
  shortCode: json['short_code'] as String?,
  status: json['status'] as String,
  type: json['type'] as String,
  channel: json['channel'] as String,
  branchId: (json['branch_id'] as num?)?.toInt(),
  hallRef: json['hall_ref'] as String?,
  tableRef: json['table_ref'] as String?,
  guestsCount: (json['guests_count'] as num?)?.toInt(),
  notes: json['notes'] as String?,
  itemsTotal: (json['items_total'] as num).toInt(),
  itemsReady: (json['items_ready'] as num).toInt(),
  items: (json['items'] as List<dynamic>)
      .map((e) => Items52.fromJson(e as Map<String, dynamic>))
      .toList(),
  createdAt: json['created_at'] as String?,
  acceptedAt: json['accepted_at'] as String?,
  preparingAt: json['preparing_at'] as String?,
  readyAt: json['ready_at'] as String?,
  canceledAt: json['canceled_at'] as String?,
  waitingSeconds: (json['waiting_seconds'] as num).toInt(),
  queueNumber: (json['queue_number'] as num?)?.toInt(),
  pickupStatus: json['pickup_status'] == null
      ? null
      : PickupStatus.fromJson(json['pickup_status'] as String),
);

Map<String, dynamic> _$Data258ToJson(Data258 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'short_code': instance.shortCode,
  'status': instance.status,
  'type': instance.type,
  'channel': instance.channel,
  'branch_id': instance.branchId,
  'hall_ref': instance.hallRef,
  'table_ref': instance.tableRef,
  'guests_count': instance.guestsCount,
  'notes': instance.notes,
  'items_total': instance.itemsTotal,
  'items_ready': instance.itemsReady,
  'items': instance.items,
  'created_at': instance.createdAt,
  'accepted_at': instance.acceptedAt,
  'preparing_at': instance.preparingAt,
  'ready_at': instance.readyAt,
  'canceled_at': instance.canceledAt,
  'waiting_seconds': instance.waitingSeconds,
  'queue_number': instance.queueNumber,
  'pickup_status': instance.pickupStatus,
};
