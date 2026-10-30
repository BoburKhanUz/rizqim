// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data229.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data229 _$Data229FromJson(Map<String, dynamic> json) => Data229(
  waiter: json['waiter'] == null
      ? null
      : Waiter22.fromJson(json['waiter'] as Map<String, dynamic>),
  shortCode: json['short_code'] as String?,
  status: json['status'] as String,
  channel: json['channel'] as String,
  type: json['type'] as String,
  storeId: (json['store_id'] as num).toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  version: (json['version'] as num).toInt(),
  hall: json['hall'] == null
      ? null
      : Hall19.fromJson(json['hall'] as Map<String, dynamic>),
  table: json['table'] == null
      ? null
      : Table19.fromJson(json['table'] as Map<String, dynamic>),
  hallRef: json['hall_ref'] as String?,
  tableRef: json['table_ref'] as String?,
  tableLabel: json['table_label'] as String?,
  guestsCount: (json['guests_count'] as num?)?.toInt(),
  uuid: json['uuid'] as String,
  openedBy: (json['opened_by'] as num).toInt(),
  deviceId: (json['device_id'] as num?)?.toInt(),
  bookingId: (json['booking_id'] as num?)?.toInt(),
  bookingUuid: json['booking_uuid'] as String?,
  depositAvailable: json['deposit_available'] as String,
  billLocked: json['bill_locked'] as bool,
  billRequestedAt: json['bill_requested_at'] as String?,
  notes: json['notes'] as String?,
  openedAt: json['opened_at'] as String?,
  updatedAt: json['updated_at'] as String?,
  subtotal: json['subtotal'] as String,
  discount: json['discount'] as String,
  discountKind: json['discount_kind'] == null
      ? null
      : DiscountKind.fromJson(json['discount_kind'] as String?),
  items: (json['items'] as List<dynamic>)
      .map((e) => Items42.fromJson(e as Map<String, dynamic>))
      .toList(),
  discountReason: json['discount_reason'] as String?,
  discountBy: (json['discount_by'] as num?)?.toInt(),
  serviceChargeAmount: json['service_charge_amount'] as String,
  placeFeeAmount: json['place_fee_amount'] as String,
  roomFeeAmount: json['room_fee_amount'] as String,
  minSpendTopupAmount: json['min_spend_topup_amount'] as String,
  totalPrice: json['total_price'] as String,
  charges: Charges21.fromJson(json['charges'] as Map<String, dynamic>),
  offline: json['offline'] == null
      ? null
      : Offline17.fromJson(json['offline'] as Map<String, dynamic>),
  queue: json['queue'] == null
      ? null
      : Queue20.fromJson(json['queue'] as Map<String, dynamic>),
  rounds: (json['rounds'] as num).toInt(),
  courses: (json['courses'] as List<dynamic>)
      .map((e) => Courses16.fromJson(e as Map<String, dynamic>))
      .toList(),
  weightPending: (json['weight_pending'] as num).toInt(),
  discountValue: json['discount_value'] as String?,
);

Map<String, dynamic> _$Data229ToJson(Data229 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'short_code': instance.shortCode,
  'status': instance.status,
  'channel': instance.channel,
  'type': instance.type,
  'store_id': instance.storeId,
  'branch_id': instance.branchId,
  'version': instance.version,
  'hall': instance.hall,
  'table': instance.table,
  'hall_ref': instance.hallRef,
  'table_ref': instance.tableRef,
  'table_label': instance.tableLabel,
  'guests_count': instance.guestsCount,
  'waiter': instance.waiter,
  'opened_by': instance.openedBy,
  'device_id': instance.deviceId,
  'booking_id': instance.bookingId,
  'booking_uuid': instance.bookingUuid,
  'deposit_available': instance.depositAvailable,
  'bill_locked': instance.billLocked,
  'bill_requested_at': instance.billRequestedAt,
  'notes': instance.notes,
  'opened_at': instance.openedAt,
  'updated_at': instance.updatedAt,
  'subtotal': instance.subtotal,
  'discount': instance.discount,
  'discount_kind': instance.discountKind,
  'discount_value': instance.discountValue,
  'discount_reason': instance.discountReason,
  'discount_by': instance.discountBy,
  'service_charge_amount': instance.serviceChargeAmount,
  'place_fee_amount': instance.placeFeeAmount,
  'room_fee_amount': instance.roomFeeAmount,
  'min_spend_topup_amount': instance.minSpendTopupAmount,
  'total_price': instance.totalPrice,
  'charges': instance.charges,
  'offline': instance.offline,
  'queue': instance.queue,
  'rounds': instance.rounds,
  'courses': instance.courses,
  'weight_pending': instance.weightPending,
  'items': instance.items,
};
