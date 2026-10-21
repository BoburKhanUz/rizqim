// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'applications.dart';
import 'booking.dart';
import 'booking_payment_status.dart';

part 'booking_payment.g.dart';

@JsonSerializable()
class BookingPayment {
  const BookingPayment({
    required this.id,
    required this.uuid,
    required this.bookingId,
    required this.storeId,
    required this.branchId,
    required this.orderId,
    required this.amount,
    required this.tender,
    required this.cardType,
    required this.transactionId,
    required this.status,
    required this.fiscalReceiptUuid,
    required this.fiscalStatus,
    required this.appliedAmount,
    required this.appliedBillUuid,
    required this.applications,
    required this.refundedAmount,
    required this.retainedAmount,
    required this.settleReason,
    required this.settleReceiptUuid,
    required this.settleFiscalStatus,
    required this.settledAt,
    required this.settledBy,
    required this.approvedBy,
    required this.deviceId,
    required this.userId,
    required this.idempotencyKey,
    required this.createdAt,
    required this.updatedAt,
    this.booking,
  });

  factory BookingPayment.fromJson(Map<String, Object?> json) =>
      _$BookingPaymentFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'booking_id')
  final int bookingId;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'order_id')
  final int? orderId;
  final String amount;
  final String tender;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  final BookingPaymentStatus status;
  @JsonKey(name: 'fiscal_receipt_uuid')
  final String? fiscalReceiptUuid;
  @JsonKey(name: 'fiscal_status')
  final String? fiscalStatus;
  @JsonKey(name: 'applied_amount')
  final String appliedAmount;
  @JsonKey(name: 'applied_bill_uuid')
  final String? appliedBillUuid;
  final List<Applications>? applications;
  @JsonKey(name: 'refunded_amount')
  final String refundedAmount;
  @JsonKey(name: 'retained_amount')
  final String retainedAmount;
  @JsonKey(name: 'settle_reason')
  final String? settleReason;
  @JsonKey(name: 'settle_receipt_uuid')
  final String? settleReceiptUuid;
  @JsonKey(name: 'settle_fiscal_status')
  final String? settleFiscalStatus;
  @JsonKey(name: 'settled_at')
  final DateTime? settledAt;
  @JsonKey(name: 'settled_by')
  final int? settledBy;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  @JsonKey(name: 'device_id')
  final int? deviceId;
  @JsonKey(name: 'user_id')
  final int? userId;
  @JsonKey(name: 'idempotency_key')
  final String? idempotencyKey;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Booking? booking;

  Map<String, Object?> toJson() => _$BookingPaymentToJson(this);
}
