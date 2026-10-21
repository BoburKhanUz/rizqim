// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data56.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data56 _$Data56FromJson(Map<String, dynamic> json) => Data56(
  uuid: json['uuid'] as String,
  provider: json['provider'] as String,
  providerTransactionId: json['provider_transaction_id'] as String?,
  status: json['status'] as String,
  amount: json['amount'] as String,
  paidAt: json['paid_at'] as String?,
  cancelledAt: json['cancelled_at'] as String?,
  createdAt: json['created_at'] as String,
  order: Order3.fromJson(json['order'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data56ToJson(Data56 instance) => <String, dynamic>{
  'uuid': instance.uuid,
  'provider': instance.provider,
  'provider_transaction_id': instance.providerTransactionId,
  'status': instance.status,
  'amount': instance.amount,
  'paid_at': instance.paidAt,
  'cancelled_at': instance.cancelledAt,
  'created_at': instance.createdAt,
  'order': instance.order,
};
