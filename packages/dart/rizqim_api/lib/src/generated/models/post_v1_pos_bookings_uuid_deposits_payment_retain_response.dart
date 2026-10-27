// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data154.dart';

part 'post_v1_pos_bookings_uuid_deposits_payment_retain_response.g.dart';

@JsonSerializable()
class PostV1PosBookingsUuidDepositsPaymentRetainResponse {
  const PostV1PosBookingsUuidDepositsPaymentRetainResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBookingsUuidDepositsPaymentRetainResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBookingsUuidDepositsPaymentRetainResponseFromJson(json);

  final bool success;
  final String message;
  final Data154 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBookingsUuidDepositsPaymentRetainResponseToJson(this);
}
