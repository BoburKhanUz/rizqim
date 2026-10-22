// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'bookings2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Bookings2 _$Bookings2FromJson(Map<String, dynamic> json) => Bookings2(
  uuid: json['uuid'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  diningTableId: (json['dining_table_id'] as num?)?.toInt(),
  tableIds: (json['table_ids'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  startsAt: json['starts_at'] as String,
  endsAt: json['ends_at'] as String,
  guestsPlanned: (json['guests_planned'] as num).toInt(),
  eventType: json['event_type'] as String,
  eventTypeLabel: json['event_type_label'] as String,
  contactName: json['contact_name'] as String,
  contactPhone: json['contact_phone'] as String?,
  source: json['source'] as String,
  sourceLabel: json['source_label'] as String,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  minSpend: json['min_spend'] as String?,
  note: json['note'] as String?,
  orderUuid: json['order_uuid'] as String?,
  depositTotal: json['deposit_total'] as String,
  depositAvailable: json['deposit_available'] as String,
  depositUnsettled: json['deposit_unsettled'] as bool,
  itemsCount: (json['items_count'] as num).toInt(),
  cancelReason: json['cancel_reason'] as String?,
  version: (json['version'] as num).toInt(),
  createdBy: (json['created_by'] as num?)?.toInt(),
  confirmedAt: json['confirmed_at'] as String?,
  arrivedAt: json['arrived_at'] as String?,
  completedAt: json['completed_at'] as String?,
  canceledAt: json['canceled_at'] as String?,
  createdAt: json['created_at'] as String?,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$Bookings2ToJson(Bookings2 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'branch_id': instance.branchId,
  'dining_table_id': instance.diningTableId,
  'table_ids': instance.tableIds,
  'starts_at': instance.startsAt,
  'ends_at': instance.endsAt,
  'guests_planned': instance.guestsPlanned,
  'event_type': instance.eventType,
  'event_type_label': instance.eventTypeLabel,
  'contact_name': instance.contactName,
  'contact_phone': instance.contactPhone,
  'source': instance.source,
  'source_label': instance.sourceLabel,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'min_spend': instance.minSpend,
  'note': instance.note,
  'order_uuid': instance.orderUuid,
  'deposit_total': instance.depositTotal,
  'deposit_available': instance.depositAvailable,
  'deposit_unsettled': instance.depositUnsettled,
  'items_count': instance.itemsCount,
  'cancel_reason': instance.cancelReason,
  'version': instance.version,
  'created_by': instance.createdBy,
  'confirmed_at': instance.confirmedAt,
  'arrived_at': instance.arrivedAt,
  'completed_at': instance.completedAt,
  'canceled_at': instance.canceledAt,
  'created_at': instance.createdAt,
  'updated_at': instance.updatedAt,
};
