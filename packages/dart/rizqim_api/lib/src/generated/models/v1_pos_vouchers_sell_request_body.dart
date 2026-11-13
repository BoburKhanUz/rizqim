// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tender2.dart';

part 'v1_pos_vouchers_sell_request_body.g.dart';

@JsonSerializable()
class V1PosVouchersSellRequestBody {
  const V1PosVouchersSellRequestBody({
    required this.faceValue,
    required this.tender,
    this.cardType,
    this.transactionId,
    this.reference,
    this.buyerName,
    this.buyerPhone,
    this.validUntil,
    this.note,
    this.shiftUuid,
    this.branchId,
  });

  factory V1PosVouchersSellRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosVouchersSellRequestBodyFromJson(json);

  @JsonKey(name: 'face_value')
  final num faceValue;
  final Tender2 tender;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  final String? reference;
  @JsonKey(name: 'buyer_name')
  final String? buyerName;
  @JsonKey(name: 'buyer_phone')
  final String? buyerPhone;
  @JsonKey(name: 'valid_until')
  final DateTime? validUntil;
  final String? note;
  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() => _$V1PosVouchersSellRequestBodyToJson(this);
}
