// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payments21.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payments21 _$Payments21FromJson(Map<String, dynamic> json) => Payments21(
  uuid: json['uuid'] as String,
  tender: json['tender'] as String,
  amount: json['amount'] as String,
  tenderedAmount: json['tendered_amount'] as String?,
  changeAmount: json['change_amount'] as String?,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  reference: json['reference'] as String?,
  status: json['status'] as String,
  paidAt: json['paid_at'] as String?,
);

Map<String, dynamic> _$Payments21ToJson(Payments21 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'tender': instance.tender,
      'amount': instance.amount,
      'tendered_amount': instance.tenderedAmount,
      'change_amount': instance.changeAmount,
      'card_type': instance.cardType,
      'transaction_id': instance.transactionId,
      'reference': instance.reference,
      'status': instance.status,
      'paid_at': instance.paidAt,
    };
