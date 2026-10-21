// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data159.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data159 _$Data159FromJson(Map<String, dynamic> json) => Data159(
  uuid: json['uuid'] as String,
  orderUuid: json['order_uuid'] as String?,
  billUuid: json['bill_uuid'] as String?,
  paymentUuid: json['payment_uuid'] as String?,
  amount: json['amount'] as String,
  method: json['method'] as String,
  reason: json['reason'] as String,
  approvedBy: (json['approved_by'] as num?)?.toInt(),
  fiscal: Fiscal11.fromJson(json['fiscal'] as Map<String, dynamic>),
  createdAt: json['created_at'] as String?,
);

Map<String, dynamic> _$Data159ToJson(Data159 instance) => <String, dynamic>{
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
