// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'hall19.dart';
import 'table19.dart';
import 'waiter22.dart';
import 'discount_kind.dart';
import 'pickup20.dart';
import 'charges22.dart';
import 'offline18.dart';
import 'queue20.dart';
import 'courses17.dart';
import 'items43.dart';

part 'data244.g.dart';

@JsonSerializable()
class Data244 {
  const Data244({
    required this.openedBy,
    required this.shortCode,
    required this.status,
    required this.channel,
    required this.type,
    required this.storeId,
    required this.branchId,
    required this.version,
    required this.hall,
    required this.table,
    required this.hallRef,
    required this.tableRef,
    required this.tableLabel,
    required this.guestsCount,
    required this.waiter,
    required this.uuid,
    required this.deviceId,
    required this.bookingId,
    required this.bookingUuid,
    required this.depositAvailable,
    required this.billLocked,
    required this.billRequestedAt,
    required this.notes,
    required this.openedAt,
    required this.updatedAt,
    required this.subtotal,
    required this.discount,
    required this.discountKind,
    required this.discountValue,
    required this.items,
    required this.discountBy,
    required this.serviceChargeAmount,
    required this.placeFeeAmount,
    required this.roomFeeAmount,
    required this.minSpendTopupAmount,
    required this.packagingAmount,
    required this.pickup,
    required this.totalPrice,
    required this.charges,
    required this.offline,
    required this.queue,
    required this.rounds,
    required this.courses,
    required this.weightPending,
    required this.discountReason,
  });

  factory Data244.fromJson(Map<String, Object?> json) =>
      _$Data244FromJson(json);

  final String uuid;
  @JsonKey(name: 'short_code')
  final String? shortCode;
  final String status;
  final String channel;
  final String type;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final int version;
  final Hall19? hall;
  final Table19? table;
  @JsonKey(name: 'hall_ref')
  final String? hallRef;
  @JsonKey(name: 'table_ref')
  final String? tableRef;
  @JsonKey(name: 'table_label')
  final String? tableLabel;
  @JsonKey(name: 'guests_count')
  final int? guestsCount;
  final Waiter22? waiter;
  @JsonKey(name: 'opened_by')
  final int openedBy;
  @JsonKey(name: 'device_id')
  final int? deviceId;
  @JsonKey(name: 'booking_id')
  final int? bookingId;
  @JsonKey(name: 'booking_uuid')
  final String? bookingUuid;
  @JsonKey(name: 'deposit_available')
  final String depositAvailable;
  @JsonKey(name: 'bill_locked')
  final bool billLocked;
  @JsonKey(name: 'bill_requested_at')
  final String? billRequestedAt;
  final String? notes;
  @JsonKey(name: 'opened_at')
  final String? openedAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;
  final String subtotal;
  final String discount;
  @JsonKey(name: 'discount_kind')
  final DiscountKind? discountKind;
  @JsonKey(name: 'discount_value')
  final String? discountValue;
  @JsonKey(name: 'discount_reason')
  final String? discountReason;
  @JsonKey(name: 'discount_by')
  final int? discountBy;
  @JsonKey(name: 'service_charge_amount')
  final String serviceChargeAmount;
  @JsonKey(name: 'place_fee_amount')
  final String placeFeeAmount;
  @JsonKey(name: 'room_fee_amount')
  final String roomFeeAmount;
  @JsonKey(name: 'min_spend_topup_amount')
  final String minSpendTopupAmount;
  @JsonKey(name: 'packaging_amount')
  final String packagingAmount;
  final Pickup20? pickup;
  @JsonKey(name: 'total_price')
  final String totalPrice;
  final Charges22 charges;
  final Offline18? offline;
  final Queue20? queue;
  final int rounds;
  final List<Courses17> courses;
  @JsonKey(name: 'weight_pending')
  final int weightPending;
  final List<Items43> items;

  Map<String, Object?> toJson() => _$Data244ToJson(this);
}
