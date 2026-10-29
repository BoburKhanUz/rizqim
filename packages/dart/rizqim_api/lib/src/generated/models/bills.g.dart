// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'bills.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Bills _$BillsFromJson(Map<String, dynamic> json) => Bills(
  uuid: json['uuid'] as String,
  orderUuid: json['order_uuid'] as String,
  number: (json['number'] as num).toInt(),
  status: json['status'] as String,
  splitMode: json['split_mode'] as String,
  subtotal: json['subtotal'] as String,
  discount: json['discount'] as String,
  roomFeeAmount: json['room_fee_amount'] as String,
  minSpendTopupAmount: json['min_spend_topup_amount'] as String,
  serviceChargeAmount: json['service_charge_amount'] as String,
  placeFeeAmount: json['place_fee_amount'] as String,
  total: json['total'] as String,
  paidAmount: json['paid_amount'] as String,
  dueAmount: json['due_amount'] as String,
  guestsShare: (json['guests_share'] as num?)?.toInt(),
  seatNo: (json['seat_no'] as num?)?.toInt(),
  perGuestAmount: json['per_guest_amount'] as String?,
  printedCount: (json['printed_count'] as num).toInt(),
  printedAt: json['printed_at'] as String?,
  paidAt: json['paid_at'] as String?,
  fiscal: Fiscal10.fromJson(json['fiscal'] as Map<String, dynamic>),
  items: (json['items'] as List<dynamic>)
      .map((e) => Items24.fromJson(e as Map<String, dynamic>))
      .toList(),
  payments: (json['payments'] as List<dynamic>)
      .map((e) => Payments29.fromJson(e as Map<String, dynamic>))
      .toList(),
  tipsAmount: json['tips_amount'] as String,
  tips: (json['tips'] as List<dynamic>)
      .map((e) => Tips6.fromJson(e as Map<String, dynamic>))
      .toList(),
  serviceChargeNotice: json['service_charge_notice'] as String?,
  ticket: json['ticket'] == null
      ? null
      : Ticket8.fromJson(json['ticket'] as Map<String, dynamic>),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$BillsToJson(Bills instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'order_uuid': instance.orderUuid,
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
  'paid_amount': instance.paidAmount,
  'due_amount': instance.dueAmount,
  'guests_share': instance.guestsShare,
  'seat_no': instance.seatNo,
  'per_guest_amount': instance.perGuestAmount,
  'printed_count': instance.printedCount,
  'printed_at': instance.printedAt,
  'paid_at': instance.paidAt,
  'fiscal': instance.fiscal,
  'items': instance.items,
  'payments': instance.payments,
  'tips_amount': instance.tipsAmount,
  'tips': instance.tips,
  'service_charge_notice': instance.serviceChargeNotice,
  'ticket': instance.ticket,
  'created_at': instance.createdAt,
};
