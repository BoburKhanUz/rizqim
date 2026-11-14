// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_banquets_uuid_payments_row_bank_transfer_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBanquetsUuidPaymentsRowBankTransferRequestBody
_$V1PosBanquetsUuidPaymentsRowBankTransferRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBanquetsUuidPaymentsRowBankTransferRequestBody(
  reference: json['reference'] as String,
  amount: json['amount'] as num?,
  paidOn: json['paid_on'] == null
      ? null
      : DateTime.parse(json['paid_on'] as String),
);

Map<String, dynamic>
_$V1PosBanquetsUuidPaymentsRowBankTransferRequestBodyToJson(
  V1PosBanquetsUuidPaymentsRowBankTransferRequestBody instance,
) => <String, dynamic>{
  'amount': instance.amount,
  'reference': instance.reference,
  'paid_on': instance.paidOn?.toIso8601String(),
};
