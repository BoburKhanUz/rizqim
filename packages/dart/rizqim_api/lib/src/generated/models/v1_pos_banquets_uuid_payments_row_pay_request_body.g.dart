// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_banquets_uuid_payments_row_pay_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBanquetsUuidPaymentsRowPayRequestBody
_$V1PosBanquetsUuidPaymentsRowPayRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosBanquetsUuidPaymentsRowPayRequestBody(
  tender: Tender.fromJson(json['tender'] as String),
  amount: json['amount'] as num?,
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  reference: json['reference'] as String?,
  shiftUuid: json['shift_uuid'] as String?,
);

Map<String, dynamic> _$V1PosBanquetsUuidPaymentsRowPayRequestBodyToJson(
  V1PosBanquetsUuidPaymentsRowPayRequestBody instance,
) => <String, dynamic>{
  'amount': instance.amount,
  'tender': instance.tender,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
  'reference': instance.reference,
  'shift_uuid': instance.shiftUuid,
};
