// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order.dart';
import 'payment_status.dart';

part 'payment.g.dart';

@JsonSerializable()
class Payment {
  const Payment({
    required this.id,
    required this.uuid,
    required this.orderId,
    required this.provider,
    required this.providerTransactionId,
    required this.amount,
    required this.status,
    required this.payload,
    required this.paidAt,
    required this.cancelledAt,
    required this.expiredAt,
    required this.createdAt,
    required this.updatedAt,
    this.order,
  });

  factory Payment.fromJson(Map<String, Object?> json) =>
      _$PaymentFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'order_id')
  final int? orderId;
  final String provider;
  @JsonKey(name: 'provider_transaction_id')
  final String? providerTransactionId;
  final String amount;
  final PaymentStatus status;
  final Map<String, dynamic>? payload;
  @JsonKey(name: 'paid_at')
  final DateTime? paidAt;
  @JsonKey(name: 'cancelled_at')
  final DateTime? cancelledAt;
  @JsonKey(name: 'expired_at')
  final DateTime? expiredAt;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Order? order;

  Map<String, Object?> toJson() => _$PaymentToJson(this);
}
