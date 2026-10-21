// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data155.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data155 _$Data155FromJson(Map<String, dynamic> json) => Data155(
  uuid: json['uuid'] as String,
  shortCode: json['short_code'] as String?,
  status: json['status'] as String,
  channel: json['channel'] as String,
  type: json['type'] as String,
  storeId: (json['store_id'] as num).toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  version: (json['version'] as num).toInt(),
  hall: json['hall'] == null
      ? null
      : Hall7.fromJson(json['hall'] as Map<String, dynamic>),
  table: json['table'] == null
      ? null
      : Table7.fromJson(json['table'] as Map<String, dynamic>),
  hallRef: json['hall_ref'] as String?,
  tableRef: json['table_ref'] as String?,
  guestsCount: (json['guests_count'] as num?)?.toInt(),
  waiter: json['waiter'] == null
      ? null
      : Waiter9.fromJson(json['waiter'] as Map<String, dynamic>),
  openedBy: (json['opened_by'] as num).toInt(),
  deviceId: (json['device_id'] as num?)?.toInt(),
  bookingId: (json['booking_id'] as num?)?.toInt(),
  billLocked: json['bill_locked'] as bool,
  notes: json['notes'] as String?,
  openedAt: json['opened_at'] as String?,
  updatedAt: json['updated_at'] as String?,
  subtotal: json['subtotal'] as String,
  discount: json['discount'] as String,
  serviceChargeAmount: json['service_charge_amount'] as String,
  placeFeeAmount: json['place_fee_amount'] as String,
  roomFeeAmount: json['room_fee_amount'] as String,
  minSpendTopupAmount: json['min_spend_topup_amount'] as String,
  totalPrice: json['total_price'] as String,
  charges: Charges11.fromJson(json['charges'] as Map<String, dynamic>),
  rounds: (json['rounds'] as num).toInt(),
  items: (json['items'] as List<dynamic>)
      .map((e) => Items29.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data155ToJson(Data155 instance) => <String, dynamic>{
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
  'guests_count': instance.guestsCount,
  'waiter': instance.waiter,
  'opened_by': instance.openedBy,
  'device_id': instance.deviceId,
  'booking_id': instance.bookingId,
  'bill_locked': instance.billLocked,
  'notes': instance.notes,
  'opened_at': instance.openedAt,
  'updated_at': instance.updatedAt,
  'subtotal': instance.subtotal,
  'discount': instance.discount,
  'service_charge_amount': instance.serviceChargeAmount,
  'place_fee_amount': instance.placeFeeAmount,
  'room_fee_amount': instance.roomFeeAmount,
  'min_spend_topup_amount': instance.minSpendTopupAmount,
  'total_price': instance.totalPrice,
  'charges': instance.charges,
  'rounds': instance.rounds,
  'items': instance.items,
};
