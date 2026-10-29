// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'tenders.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Tenders _$TendersFromJson(Map<String, dynamic> json) => Tenders(
  tender: Tender.fromJson(json['tender'] as String),
  amount: json['amount'] as num,
  tenderedAmount: json['tendered_amount'] as num?,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  cardLast4: json['card_last4'] as String?,
  reference: json['reference'] as String?,
  accountUuid: json['account_uuid'] as String?,
);

Map<String, dynamic> _$TendersToJson(Tenders instance) => <String, dynamic>{
  'tender': instance.tender,
  'amount': instance.amount,
  'tendered_amount': instance.tenderedAmount,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
  'card_last4': instance.cardLast4,
  'reference': instance.reference,
  'account_uuid': instance.accountUuid,
};
