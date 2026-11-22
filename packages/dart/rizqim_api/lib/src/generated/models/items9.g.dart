// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'items9.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Items9 _$Items9FromJson(Map<String, dynamic> json) => Items9(
  uuid: json['uuid'] as String,
  billUuid: json['bill_uuid'] as String,
  orderUuid: json['order_uuid'] as String?,
  branchId: (json['branch_id'] as num).toInt(),
  provider: json['provider'] as String,
  providerLabel: json['provider_label'] as String,
  amount: json['amount'] as String,
  transactionId: json['transaction_id'] as String?,
  reason: json['reason'] as String?,
  status: json['status'] as String,
  createdAt: json['created_at'] as String?,
  unmatchedAt: json['unmatched_at'] as String?,
);

Map<String, dynamic> _$Items9ToJson(Items9 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'bill_uuid': instance.billUuid,
  'order_uuid': instance.orderUuid,
  'branch_id': instance.branchId,
  'provider': instance.provider,
  'provider_label': instance.providerLabel,
  'amount': instance.amount,
  'transaction_id': instance.transactionId,
  'reason': instance.reason,
  'status': instance.status,
  'created_at': instance.createdAt,
  'unmatched_at': instance.unmatchedAt,
};
