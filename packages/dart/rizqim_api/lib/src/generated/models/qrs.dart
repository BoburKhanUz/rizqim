// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'display2.dart';

part 'qrs.g.dart';

@JsonSerializable()
class Qrs {
  const Qrs({
    required this.uuid,
    required this.billUuid,
    required this.orderUuid,
    required this.provider,
    required this.providerLabel,
    required this.amount,
    required this.status,
    required this.payload,
    required this.display,
    required this.expiresAt,
    required this.held,
    required this.paidAt,
    required this.cancelledAt,
    required this.cancelReason,
    required this.paymentUuid,
    required this.refundedAmount,
    required this.simulated,
    required this.createdAt,
  });

  factory Qrs.fromJson(Map<String, Object?> json) => _$QrsFromJson(json);

  final String uuid;
  @JsonKey(name: 'bill_uuid')
  final String billUuid;
  @JsonKey(name: 'order_uuid')
  final String? orderUuid;
  final String provider;
  @JsonKey(name: 'provider_label')
  final String providerLabel;
  final String amount;
  final String status;
  final String payload;
  final Display2 display;
  @JsonKey(name: 'expires_at')
  final String expiresAt;
  final bool held;
  @JsonKey(name: 'paid_at')
  final String? paidAt;
  @JsonKey(name: 'cancelled_at')
  final String? cancelledAt;
  @JsonKey(name: 'cancel_reason')
  final String? cancelReason;
  @JsonKey(name: 'payment_uuid')
  final String? paymentUuid;
  @JsonKey(name: 'refunded_amount')
  final String refundedAmount;
  final bool simulated;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$QrsToJson(this);
}
