// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payments25.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payments25 _$Payments25FromJson(Map<String, dynamic> json) => Payments25(
  uuid: json['uuid'] as String,
  amount: json['amount'] as String,
  tender: json['tender'] as String,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  remaining: json['remaining'] as String,
  appliedAmount: json['applied_amount'] as String,
  refundedAmount: json['refunded_amount'] as String,
  retainedAmount: json['retained_amount'] as String,
  fiscalReceiptUuid: json['fiscal_receipt_uuid'] as String?,
  fiscalStatus: json['fiscal_status'] as String?,
  settleReason: json['settle_reason'] as String?,
  settleReceiptUuid: json['settle_receipt_uuid'] as String?,
  settleFiscalStatus: json['settle_fiscal_status'] as String?,
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  userId: (json['user_id'] as num?)?.toInt(),
  createdAt: json['created_at'] as String?,
  settledAt: json['settled_at'] as String?,
);

Map<String, dynamic> _$Payments25ToJson(Payments25 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'amount': instance.amount,
      'tender': instance.tender,
      'card_type': instance.cardType,
      'transaction_id': instance.transactionId,
      'status': instance.status,
      'status_label': instance.statusLabel,
      'remaining': instance.remaining,
      'applied_amount': instance.appliedAmount,
      'refunded_amount': instance.refundedAmount,
      'retained_amount': instance.retainedAmount,
      'fiscal_receipt_uuid': instance.fiscalReceiptUuid,
      'fiscal_status': instance.fiscalStatus,
      'settle_reason': instance.settleReason,
      'settle_receipt_uuid': instance.settleReceiptUuid,
      'settle_fiscal_status': instance.settleFiscalStatus,
      'approved_by': instance.approvedBy,
      'user_id': instance.userId,
      'created_at': instance.createdAt,
      'settled_at': instance.settledAt,
    };
