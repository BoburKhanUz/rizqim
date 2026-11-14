// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_pos_vouchers_sell_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1PosVouchersSellRequestBody _$V1PosVouchersSellRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1PosVouchersSellRequestBody(
  faceValue: json['face_value'] as num,
  tender: Tender.fromJson(json['tender'] as String),
  cardType: json['card_type'] as String?,
  transactionId: json['transaction_id'] as String?,
  reference: json['reference'] as String?,
  buyerName: json['buyer_name'] as String?,
  buyerPhone: json['buyer_phone'] as String?,
  validUntil: json['valid_until'] == null
      ? null
      : DateTime.parse(json['valid_until'] as String),
  note: json['note'] as String?,
  shiftUuid: json['shift_uuid'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
);

Map<String, dynamic> _$V1PosVouchersSellRequestBodyToJson(
  V1PosVouchersSellRequestBody instance,
) => <String, dynamic>{
  'face_value': instance.faceValue,
  'tender': instance.tender,
  'card_type': instance.cardType,
  'transaction_id': instance.transactionId,
  'reference': instance.reference,
  'buyer_name': instance.buyerName,
  'buyer_phone': instance.buyerPhone,
  'valid_until': instance.validUntil?.toIso8601String(),
  'note': instance.note,
  'shift_uuid': instance.shiftUuid,
  'branch_id': instance.branchId,
};
