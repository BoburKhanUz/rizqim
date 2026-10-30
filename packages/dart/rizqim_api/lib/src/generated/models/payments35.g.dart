// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payments35.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Payments35 _$Payments35FromJson(Map<String, dynamic> json) => Payments35(
  uuid: json['uuid'] as String,
  tender: json['tender'] as String,
  amount: json['amount'] as String,
  tenderedAmount: json['tendered_amount'] as String?,
  changeAmount: json['change_amount'] as String?,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  cardLast4: json['card_last4'] as String?,
  reference: json['reference'] as String?,
  status: json['status'] as String,
  paidAt: json['paid_at'] as String?,
  tipAmount: json['tip_amount'] as String,
);

Map<String, dynamic> _$Payments35ToJson(Payments35 instance) =>
    <String, dynamic>{
      'uuid': instance.uuid,
      'tender': instance.tender,
      'amount': instance.amount,
      'tendered_amount': instance.tenderedAmount,
      'change_amount': instance.changeAmount,
      'card_type': instance.cardType,
      'transaction_id': instance.transactionId,
      'card_last4': instance.cardLast4,
      'reference': instance.reference,
      'status': instance.status,
      'paid_at': instance.paidAt,
      'tip_amount': instance.tipAmount,
    };
