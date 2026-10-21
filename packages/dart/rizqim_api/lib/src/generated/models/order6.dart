// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'order6.g.dart';

@JsonSerializable()
class Order6 {
  const Order6({
    required this.uuid,
    required this.status,
    required this.paymentStatus,
    required this.version,
    required this.queueNumber,
    required this.businessDate,
    required this.billLockedAt,
    required this.closedAt,
    required this.totalPrice,
    required this.billedAmount,
    required this.paidAmount,
    required this.unbilledAmount,
    required this.fiscalPending,
    required this.billsOutOfDate,
  });

  factory Order6.fromJson(Map<String, Object?> json) => _$Order6FromJson(json);

  final String uuid;
  final String status;
  @JsonKey(name: 'payment_status')
  final String paymentStatus;
  final int version;
  @JsonKey(name: 'queue_number')
  final int? queueNumber;
  @JsonKey(name: 'business_date')
  final String? businessDate;
  @JsonKey(name: 'bill_locked_at')
  final String? billLockedAt;
  @JsonKey(name: 'closed_at')
  final String? closedAt;
  @JsonKey(name: 'total_price')
  final String totalPrice;
  @JsonKey(name: 'billed_amount')
  final String billedAmount;
  @JsonKey(name: 'paid_amount')
  final String paidAmount;
  @JsonKey(name: 'unbilled_amount')
  final String? unbilledAmount;
  @JsonKey(name: 'fiscal_pending')
  final bool fiscalPending;
  @JsonKey(name: 'bills_out_of_date')
  final bool billsOutOfDate;

  Map<String, Object?> toJson() => _$Order6ToJson(this);
}
