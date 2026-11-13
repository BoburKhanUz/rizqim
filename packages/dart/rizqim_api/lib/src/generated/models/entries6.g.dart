// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'entries6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Entries6 _$Entries6FromJson(Map<String, dynamic> json) => Entries6(
  uuid: json['uuid'] as String,
  type: json['type'] as String,
  typeLabel: json['type_label'] as String,
  amount: json['amount'] as String,
  balanceAfter: json['balance_after'] as String,
  businessDate: json['business_date'] as String?,
  billUuid: json['bill_uuid'] as String?,
  refundUuid: json['refund_uuid'] as String?,
  tender: json['tender'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  shiftId: (json['shift_id'] as num?)?.toInt(),
  user: json['user'] == null
      ? null
      : User26.fromJson(json['user'] as Map<String, dynamic>),
  note: json['note'] as String?,
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Entries6ToJson(Entries6 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'type': instance.type,
  'type_label': instance.typeLabel,
  'amount': instance.amount,
  'balance_after': instance.balanceAfter,
  'business_date': instance.businessDate,
  'bill_uuid': instance.billUuid,
  'refund_uuid': instance.refundUuid,
  'tender': instance.tender,
  'branch_id': instance.branchId,
  'shift_id': instance.shiftId,
  'user': instance.user,
  'note': instance.note,
  'created_at': instance.createdAt,
};
