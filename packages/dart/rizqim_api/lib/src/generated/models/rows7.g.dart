// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rows7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rows7 _$Rows7FromJson(Map<String, dynamic> json) => Rows7(
  uuid: json['uuid'] as String,
  kind: json['kind'] as String,
  kindLabel: json['kind_label'] as String,
  dueDate: json['due_date'] as String?,
  amount: json['amount'] as String,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  overdue: json['overdue'] as bool,
  tender: json['tender'] as String?,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  reference: json['reference'] as String?,
  shiftId: (json['shift_id'] as num?)?.toInt(),
  paidAt: json['paid_at'] as String?,
  paidBy: json['paid_by'] == null
      ? null
      : PaidBy2.fromJson(json['paid_by'] as Map<String, dynamic>),
  fiscal: Fiscal10.fromJson(json['fiscal'] as Map<String, dynamic>),
  refundedAmount: json['refunded_amount'] as String,
  retainedAmount: json['retained_amount'] as String,
  settleReceiptUuid: json['settle_receipt_uuid'] as String?,
  note: json['note'] as String?,
);

Map<String, dynamic> _$Rows7ToJson(Rows7 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'kind': instance.kind,
  'kind_label': instance.kindLabel,
  'due_date': instance.dueDate,
  'amount': instance.amount,
  'status': instance.status,
  'status_label': instance.statusLabel,
  'overdue': instance.overdue,
  'tender': instance.tender,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
  'reference': instance.reference,
  'shift_id': instance.shiftId,
  'paid_at': instance.paidAt,
  'paid_by': instance.paidBy,
  'fiscal': instance.fiscal,
  'refunded_amount': instance.refundedAmount,
  'retained_amount': instance.retainedAmount,
  'settle_receipt_uuid': instance.settleReceiptUuid,
  'note': instance.note,
};
