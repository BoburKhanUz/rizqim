// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'entry2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Entry2 _$Entry2FromJson(Map<String, dynamic> json) => Entry2(
  uuid: json['uuid'] as String,
  type: json['type'] as String,
  typeLabel: json['type_label'] as String,
  amount: json['amount'] as String,
  balanceAfter: json['balance_after'] as String,
  billUuid: json['bill_uuid'] as String?,
  tender: json['tender'] as String?,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  fiscalReceiptUuid: json['fiscal_receipt_uuid'] as String?,
  fiscalStatus: json['fiscal_status'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  user: json['user'] == null
      ? null
      : User17.fromJson(json['user'] as Map<String, dynamic>),
  approvedBy: json['approved_by'] == null
      ? null
      : ApprovedBy4.fromJson(json['approved_by'] as Map<String, dynamic>),
  note: json['note'] as String?,
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Entry2ToJson(Entry2 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'type': instance.type,
  'type_label': instance.typeLabel,
  'amount': instance.amount,
  'balance_after': instance.balanceAfter,
  'bill_uuid': instance.billUuid,
  'tender': instance.tender,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
  'fiscal_receipt_uuid': instance.fiscalReceiptUuid,
  'fiscal_status': instance.fiscalStatus,
  'branch_id': instance.branchId,
  'user': instance.user,
  'approved_by': instance.approvedBy,
  'note': instance.note,
  'created_at': instance.createdAt,
};
