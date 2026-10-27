// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data191.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data191 _$Data191FromJson(Map<String, dynamic> json) => Data191(
  uuid: json['uuid'] as String,
  orderUuid: json['order_uuid'] as String?,
  billUuid: json['bill_uuid'] as String?,
  paymentUuid: json['payment_uuid'] as String?,
  amount: json['amount'] as String,
  method: json['method'] as String,
  reason: json['reason'] as String,
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  fiscal: Fiscal15.fromJson(json['fiscal'] as Map<String, dynamic>),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Data191ToJson(Data191 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'order_uuid': instance.orderUuid,
  'bill_uuid': instance.billUuid,
  'payment_uuid': instance.paymentUuid,
  'amount': instance.amount,
  'method': instance.method,
  'reason': instance.reason,
  'approved_by': instance.approvedBy,
  'fiscal': instance.fiscal,
  'created_at': instance.createdAt,
};
