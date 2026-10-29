// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data259.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data259 _$Data259FromJson(Map<String, dynamic> json) => Data259(
  subtotal: json['subtotal'] as String,
  shortCode: json['short_code'] as String?,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  type: json['type'] as String,
  channel: json['channel'] as String,
  storeId: (json['store_id'] as num).toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  branchName: json['branch_name'] as String?,
  paymentMethod: json['payment_method'] as String,
  paymentStatus: json['payment_status'] as String,
  uuid: json['uuid'] as String,
  discount: json['discount'] as String,
  deliveryPrice: json['delivery_price'] as String,
  serviceChargeAmount: json['service_charge_amount'] as String,
  placeFeeAmount: json['place_fee_amount'] as String,
  totalPrice: json['total_price'] as String,
  notes: json['notes'] as String?,
  guestsCount: (json['guests_count'] as num?)?.toInt(),
  hallRef: json['hall_ref'] as String?,
  tableRef: json['table_ref'] as String?,
  cancelReason: json['cancel_reason'] as String?,
  cancelNote: json['cancel_note'] as String?,
  waitingSeconds: (json['waiting_seconds'] as num).toInt(),
  delivery: json['delivery'] == null
      ? null
      : Delivery7.fromJson(json['delivery'] as Map<String, dynamic>),
  courier: json['courier'] == null
      ? null
      : Courier4.fromJson(json['courier'] as Map<String, dynamic>),
  itemsTotal: (json['items_total'] as num).toInt(),
  itemsReady: (json['items_ready'] as num).toInt(),
  items: (json['items'] as List<dynamic>)
      .map((e) => Items53.fromJson(e as Map<String, dynamic>))
      .toList(),
  createdAt: json['created_at'] as String?,
  updatedAt: json['updated_at'] as String?,
  acceptedAt: json['accepted_at'] as String?,
  preparingAt: json['preparing_at'] as String?,
  readyAt: json['ready_at'] as String?,
  closedAt: json['closed_at'] as String?,
  customer: json['customer'] == null
      ? null
      : Customer4.fromJson(json['customer'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data259ToJson(Data259 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'short_code': instance.shortCode,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'type': instance.type,
  'channel': instance.channel,
  'store_id': instance.storeId,
  'branch_id': instance.branchId,
  'branch_name': instance.branchName,
  'payment_method': instance.paymentMethod,
  'payment_status': instance.paymentStatus,
  'subtotal': instance.subtotal,
  'discount': instance.discount,
  'delivery_price': instance.deliveryPrice,
  'service_charge_amount': instance.serviceChargeAmount,
  'place_fee_amount': instance.placeFeeAmount,
  'total_price': instance.totalPrice,
  'notes': instance.notes,
  'guests_count': instance.guestsCount,
  'hall_ref': instance.hallRef,
  'table_ref': instance.tableRef,
  'cancel_reason': instance.cancelReason,
  'cancel_note': instance.cancelNote,
  'customer': instance.customer,
  'delivery': instance.delivery,
  'courier': instance.courier,
  'items_total': instance.itemsTotal,
  'items_ready': instance.itemsReady,
  'items': instance.items,
  'created_at': instance.createdAt,
  'updated_at': instance.updatedAt,
  'accepted_at': instance.acceptedAt,
  'preparing_at': instance.preparingAt,
  'ready_at': instance.readyAt,
  'closed_at': instance.closedAt,
  'waiting_seconds': instance.waitingSeconds,
};
