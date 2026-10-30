// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data197.dart';

part 'post_v1_pos_bookings_uuid_deposits_payment_refund_response.g.dart';

@JsonSerializable()
class PostV1PosBookingsUuidDepositsPaymentRefundResponse {
  const PostV1PosBookingsUuidDepositsPaymentRefundResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBookingsUuidDepositsPaymentRefundResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBookingsUuidDepositsPaymentRefundResponseFromJson(json);

  final bool success;
  final String message;
  final Data197 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBookingsUuidDepositsPaymentRefundResponseToJson(this);
}
