// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'entries7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Entries7 _$Entries7FromJson(Map<String, dynamic> json) => Entries7(
  uuid: json['uuid'] as String,
  type: json['type'] as String,
  typeLabel: json['type_label'] as String,
  paymentUuid: json['payment_uuid'] as String?,
  amount: json['amount'] as String,
  tender: json['tender'] as String,
  cardType: json['card_type'] as String?,
  reference: json['reference'] as String?,
  businessDate: json['business_date'] as String?,
  shiftId: (json['shift_id'] as num?)?.toInt(),
  user: json['user'] == null
      ? null
      : User18.fromJson(json['user'] as Map<String, dynamic>),
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  reason: json['reason'] as String?,
  transferPending: json['transfer_pending'] as bool,
  fiscal: Fiscal15.fromJson(json['fiscal'] as Map<String, dynamic>),
  originalReceiptUuid: json['original_receipt_uuid'] as String?,
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Entries7ToJson(Entries7 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'type': instance.type,
  'type_label': instance.typeLabel,
  'payment_uuid': instance.paymentUuid,
  'amount': instance.amount,
  'tender': instance.tender,
  'card_type': instance.cardType,
  'reference': instance.reference,
  'business_date': instance.businessDate,
  'shift_id': instance.shiftId,
  'user': instance.user,
  'approved_by': instance.approvedBy,
  'reason': instance.reason,
  'transfer_pending': instance.transferPending,
  'fiscal': instance.fiscal,
  'original_receipt_uuid': instance.originalReceiptUuid,
  'created_at': instance.createdAt,
};
