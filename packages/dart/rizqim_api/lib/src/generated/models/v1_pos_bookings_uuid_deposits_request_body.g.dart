// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_bookings_uuid_deposits_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosBookingsUuidDepositsRequestBody
_$V1PosBookingsUuidDepositsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1PosBookingsUuidDepositsRequestBody(
      amount: json['amount'] as num,
      tender: Tender2.fromJson(json['tender'] as String),
      cardType: json['card_type'] as String?,
      transactionId: json['transaction_id'] as String?,
      approvalToken: json['approval_token'] as String?,
    );

Map<String, dynamic> _$V1PosBookingsUuidDepositsRequestBodyToJson(
  V1PosBookingsUuidDepositsRequestBody instance,
) => <String, dynamic>{
  'amount': instance.amount,
  'tender': instance.tender,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
  'approval_token': instance.approvalToken,
};
