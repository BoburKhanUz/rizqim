// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tender.dart';

part 'v1_pos_banquets_uuid_payments_row_pay_request_body.g.dart';

@JsonSerializable()
class V1PosBanquetsUuidPaymentsRowPayRequestBody {
  const V1PosBanquetsUuidPaymentsRowPayRequestBody({
    required this.tender,
    this.amount,
    this.cardType,
    this.transactionId,
    this.reference,
    this.shiftUuid,
  });

  factory V1PosBanquetsUuidPaymentsRowPayRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBanquetsUuidPaymentsRowPayRequestBodyFromJson(json);

  final num? amount;
  final Tender tender;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  final String? reference;
  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;

  Map<String, Object?> toJson() =>
      _$V1PosBanquetsUuidPaymentsRowPayRequestBodyToJson(this);
}
