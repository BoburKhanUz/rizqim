// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data173.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data173 _$Data173FromJson(Map<String, dynamic> json) => Data173(
  uuid: json['uuid'] as String,
  shortCode: json['short_code'] as String?,
  status: json['status'] as String,
  channel: json['channel'] as String,
  type: json['type'] as String,
  branchId: (json['branch_id'] as num).toInt(),
  version: (json['version'] as num).toInt(),
  hall: json['hall'] == null
      ? null
      : Hall4.fromJson(json['hall'] as Map<String, dynamic>),
  table: json['table'] == null
      ? null
      : Table4.fromJson(json['table'] as Map<String, dynamic>),
  hallRef: json['hall_ref'] as String?,
  tableRef: json['table_ref'] as String?,
  guestsCount: (json['guests_count'] as num?)?.toInt(),
  waiter: json['waiter'] == null
      ? null
      : Waiter7.fromJson(json['waiter'] as Map<String, dynamic>),
  itemsCount: (json['items_count'] as num).toInt(),
  subtotal: json['subtotal'] as String,
  totalPrice: json['total_price'] as String,
  paymentStatus: json['payment_status'] as String,
  billLocked: json['bill_locked'] as bool,
  billRequested: json['bill_requested'] as bool,
  fiscalPending: json['fiscal_pending'] as bool,
  bookingId: (json['booking_id'] as num?)?.toInt(),
  businessDate: json['business_date'] as String?,
  queue: json['queue'] == null
      ? null
      : Queue5.fromJson(json['queue'] as Map<String, dynamic>),
  openedAt: json['opened_at'] as String?,
  closedAt: json['closed_at'] as String?,
  updatedAt: json['updated_at'] as String?,
  online: json['online'] == null
      ? null
      : Online2.fromJson(json['online'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data173ToJson(Data173 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'short_code': instance.shortCode,
  'status': instance.status,
  'channel': instance.channel,
  'type': instance.type,
  'branch_id': instance.branchId,
  'version': instance.version,
  'hall': instance.hall,
  'table': instance.table,
  'hall_ref': instance.hallRef,
  'table_ref': instance.tableRef,
  'guests_count': instance.guestsCount,
  'waiter': instance.waiter,
  'items_count': instance.itemsCount,
  'subtotal': instance.subtotal,
  'total_price': instance.totalPrice,
  'payment_status': instance.paymentStatus,
  'bill_locked': instance.billLocked,
  'bill_requested': instance.billRequested,
  'fiscal_pending': instance.fiscalPending,
  'booking_id': instance.bookingId,
  'business_date': instance.businessDate,
  'queue': instance.queue,
  'opened_at': instance.openedAt,
  'closed_at': instance.closedAt,
  'updated_at': instance.updatedAt,
  'online': instance.online,
};
