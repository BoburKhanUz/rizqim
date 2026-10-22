// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tender2.dart';

part 'v1_pos_bookings_uuid_deposits_request_body.g.dart';

@JsonSerializable()
class V1PosBookingsUuidDepositsRequestBody {
  const V1PosBookingsUuidDepositsRequestBody({
    required this.amount,
    required this.tender,
    this.cardType,
    this.transactionId,
  });

  factory V1PosBookingsUuidDepositsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBookingsUuidDepositsRequestBodyFromJson(json);

  final num amount;
  final Tender2 tender;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;

  Map<String, Object?> toJson() =>
      _$V1PosBookingsUuidDepositsRequestBodyToJson(this);
}
