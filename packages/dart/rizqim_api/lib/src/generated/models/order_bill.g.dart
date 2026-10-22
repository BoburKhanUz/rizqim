// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'order_bill.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

OrderBill _$OrderBillFromJson(Map<String, dynamic> json) => OrderBill(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  storeId: (json['store_id'] as num).toInt(),
  branchId: (json['branch_id'] as num?)?.toInt(),
  orderId: (json['order_id'] as num).toInt(),
  number: (json['number'] as num).toInt(),
  status: BillStatus.fromJson(json['status'] as String),
  splitMode: json['split_mode'] as String,
  subtotal: json['subtotal'] as String,
  discount: json['discount'] as String,
  roomFeeAmount: json['room_fee_amount'] as String,
  minSpendTopupAmount: json['min_spend_topup_amount'] as String,
  serviceChargeAmount: json['service_charge_amount'] as String,
  placeFeeAmount: json['place_fee_amount'] as String,
  total: json['total'] as String,
  guestsShare: (json['guests_share'] as num?)?.toInt(),
  basis: json['basis'] as String,
  printedCount: (json['printed_count'] as num).toInt(),
  printedAt: json['printed_at'] == null
      ? null
      : DateTime.parse(json['printed_at'] as String),
  paidAt: json['paid_at'] == null
      ? null
      : DateTime.parse(json['paid_at'] as String),
  shiftId: (json['shift_id'] as num?)?.toInt(),
  fiscalReceiptUuid: json['fiscal_receipt_uuid'] as String?,
  fiscalStatus: json['fiscal_status'] as String?,
  fiscalError: json['fiscal_error'] as String?,
  createdBy: (json['created_by'] as num?)?.toInt(),
  voidedAt: json['voided_at'] == null
      ? null
      : DateTime.parse(json['voided_at'] as String),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  order: json['order'] == null
      ? null
      : Order.fromJson(json['order'] as Map<String, dynamic>),
  items: (json['items'] as List<dynamic>?)
      ?.map((e) => OrderBillItem.fromJson(e as Map<String, dynamic>))
      .toList(),
  payments: (json['payments'] as List<dynamic>?)
      ?.map((e) => Payment.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$OrderBillToJson(OrderBill instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'store_id': instance.storeId,
  'branch_id': instance.branchId,
  'order_id': instance.orderId,
  'number': instance.number,
  'status': instance.status,
  'split_mode': instance.splitMode,
  'subtotal': instance.subtotal,
  'discount': instance.discount,
  'room_fee_amount': instance.roomFeeAmount,
  'min_spend_topup_amount': instance.minSpendTopupAmount,
  'service_charge_amount': instance.serviceChargeAmount,
  'place_fee_amount': instance.placeFeeAmount,
  'total': instance.total,
  'guests_share': instance.guestsShare,
  'basis': instance.basis,
  'printed_count': instance.printedCount,
  'printed_at': instance.printedAt?.toIso8601String(),
  'paid_at': instance.paidAt?.toIso8601String(),
  'shift_id': instance.shiftId,
  'fiscal_receipt_uuid': instance.fiscalReceiptUuid,
  'fiscal_status': instance.fiscalStatus,
  'fiscal_error': instance.fiscalError,
  'created_by': instance.createdBy,
  'voided_at': instance.voidedAt?.toIso8601String(),
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'order': instance.order,
  'items': instance.items,
  'payments': instance.payments,
};
