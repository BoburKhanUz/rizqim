// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill_status.dart';
import 'order.dart';
import 'order_bill_item.dart';
import 'payment.dart';

part 'order_bill.g.dart';

@JsonSerializable()
class OrderBill {
  const OrderBill({
    required this.id,
    required this.uuid,
    required this.storeId,
    required this.branchId,
    required this.orderId,
    required this.number,
    required this.status,
    required this.splitMode,
    required this.subtotal,
    required this.discount,
    required this.roomFeeAmount,
    required this.minSpendTopupAmount,
    required this.serviceChargeAmount,
    required this.placeFeeAmount,
    required this.total,
    required this.guestsShare,
    required this.basis,
    required this.printedCount,
    required this.printedAt,
    required this.paidAt,
    required this.shiftId,
    required this.fiscalReceiptUuid,
    required this.fiscalStatus,
    required this.fiscalError,
    required this.createdBy,
    required this.voidedAt,
    required this.createdAt,
    required this.updatedAt,
    this.order,
    this.items,
    this.payments,
  });

  factory OrderBill.fromJson(Map<String, Object?> json) =>
      _$OrderBillFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'order_id')
  final int orderId;
  final int number;
  final BillStatus status;
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
  final String total;
  @JsonKey(name: 'guests_share')
  final int? guestsShare;
  final String basis;
  @JsonKey(name: 'printed_count')
  final int printedCount;
  @JsonKey(name: 'printed_at')
  final DateTime? printedAt;
  @JsonKey(name: 'paid_at')
  final DateTime? paidAt;
  @JsonKey(name: 'shift_id')
  final int? shiftId;
  @JsonKey(name: 'fiscal_receipt_uuid')
  final String? fiscalReceiptUuid;
  @JsonKey(name: 'fiscal_status')
  final String? fiscalStatus;
  @JsonKey(name: 'fiscal_error')
  final String? fiscalError;
  @JsonKey(name: 'created_by')
  final int? createdBy;
  @JsonKey(name: 'voided_at')
  final DateTime? voidedAt;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Order? order;
  final List<OrderBillItem>? items;
  final List<Payment>? payments;

  Map<String, Object?> toJson() => _$OrderBillToJson(this);
}
