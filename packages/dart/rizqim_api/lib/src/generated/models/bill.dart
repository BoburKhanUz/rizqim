// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'packaging_lines.dart';
import 'fiscal4.dart';
import 'items4.dart';
import 'payments5.dart';
import 'tips2.dart';
import 'ticket.dart';

part 'bill.g.dart';

@JsonSerializable()
class Bill {
  const Bill({
    required this.uuid,
    required this.orderUuid,
    required this.number,
    required this.status,
    required this.splitMode,
    required this.subtotal,
    required this.discount,
    required this.roomFeeAmount,
    required this.minSpendTopupAmount,
    required this.serviceChargeAmount,
    required this.placeFeeAmount,
    required this.packagingAmount,
    required this.packagingLines,
    required this.total,
    required this.paidAmount,
    required this.dueAmount,
    required this.guestsShare,
    required this.seatNo,
    required this.perGuestAmount,
    required this.printedCount,
    required this.printedAt,
    required this.paidAt,
    required this.fiscal,
    required this.items,
    required this.payments,
    required this.tipsAmount,
    required this.tips,
    required this.serviceChargeNotice,
    required this.ticket,
    required this.createdAt,
  });

  factory Bill.fromJson(Map<String, Object?> json) => _$BillFromJson(json);

  final String uuid;
  @JsonKey(name: 'order_uuid')
  final String orderUuid;
  final int number;
  final String status;
  @JsonKey(name: 'split_mode')
  final String splitMode;
  final String subtotal;
  final String discount;
  @JsonKey(name: 'room_fee_amount')
  final String roomFeeAmount;
  @JsonKey(name: 'min_spend_topup_amount')
  final String minSpendTopupAmount;
  @JsonKey(name: 'service_charge_amount')
  final String serviceChargeAmount;
  @JsonKey(name: 'place_fee_amount')
  final String placeFeeAmount;
  @JsonKey(name: 'packaging_amount')
  final String packagingAmount;
  @JsonKey(name: 'packaging_lines')
  final List<PackagingLines> packagingLines;
  final String total;
  @JsonKey(name: 'paid_amount')
  final String paidAmount;
  @JsonKey(name: 'due_amount')
  final String dueAmount;
  @JsonKey(name: 'guests_share')
  final int? guestsShare;
  @JsonKey(name: 'seat_no')
  final int? seatNo;
  @JsonKey(name: 'per_guest_amount')
  final String? perGuestAmount;
  @JsonKey(name: 'printed_count')
  final int printedCount;
  @JsonKey(name: 'printed_at')
  final String? printedAt;
  @JsonKey(name: 'paid_at')
  final String? paidAt;
  final Fiscal4 fiscal;
  final List<Items4> items;
  final List<Payments5> payments;
  @JsonKey(name: 'tips_amount')
  final String tipsAmount;
  final List<Tips2> tips;
  @JsonKey(name: 'service_charge_notice')
  final String? serviceChargeNotice;
  final Ticket? ticket;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$BillToJson(this);
}
