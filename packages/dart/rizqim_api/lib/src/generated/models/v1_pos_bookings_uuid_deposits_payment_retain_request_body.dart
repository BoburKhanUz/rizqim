// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_bookings_uuid_deposits_payment_retain_request_body.g.dart';

@JsonSerializable()
class V1PosBookingsUuidDepositsPaymentRetainRequestBody {
  const V1PosBookingsUuidDepositsPaymentRetainRequestBody({
    required this.reason,
    this.approvalToken,
  });

  factory V1PosBookingsUuidDepositsPaymentRetainRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBookingsUuidDepositsPaymentRetainRequestBodyFromJson(json);

  final String reason;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosBookingsUuidDepositsPaymentRetainRequestBodyToJson(this);
}
