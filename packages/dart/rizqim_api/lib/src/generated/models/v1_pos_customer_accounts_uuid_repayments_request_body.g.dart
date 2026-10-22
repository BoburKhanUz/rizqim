// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_customer_accounts_uuid_repayments_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosCustomerAccountsUuidRepaymentsRequestBody
_$V1PosCustomerAccountsUuidRepaymentsRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosCustomerAccountsUuidRepaymentsRequestBody(
  amount: json['amount'] as num,
  tender: Tender2.fromJson(json['tender'] as String),
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  note: json['note'] as String?,
);

Map<String, dynamic> _$V1PosCustomerAccountsUuidRepaymentsRequestBodyToJson(
  V1PosCustomerAccountsUuidRepaymentsRequestBody instance,
) => <String, dynamic>{
  'amount': instance.amount,
  'tender': instance.tender,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
  'note': instance.note,
};
