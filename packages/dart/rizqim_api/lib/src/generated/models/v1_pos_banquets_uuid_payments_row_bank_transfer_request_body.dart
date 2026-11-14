// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_banquets_uuid_payments_row_bank_transfer_request_body.g.dart';

@JsonSerializable()
class V1PosBanquetsUuidPaymentsRowBankTransferRequestBody {
  const V1PosBanquetsUuidPaymentsRowBankTransferRequestBody({
    required this.reference,
    this.amount,
    this.paidOn,
  });

  factory V1PosBanquetsUuidPaymentsRowBankTransferRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBanquetsUuidPaymentsRowBankTransferRequestBodyFromJson(json);

  final num? amount;
  final String reference;
  @JsonKey(name: 'paid_on')
  final DateTime? paidOn;

  Map<String, Object?> toJson() =>
      _$V1PosBanquetsUuidPaymentsRowBankTransferRequestBodyToJson(this);
}
