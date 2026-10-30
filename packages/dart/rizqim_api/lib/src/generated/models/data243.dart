// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'hall18.dart';
import 'table18.dart';
import 'waiter21.dart';
import 'discount_kind.dart';
import 'pickup17.dart';
import 'charges21.dart';
import 'offline17.dart';
import 'queue19.dart';
import 'courses16.dart';
import 'items42.dart';

part 'data243.g.dart';

@JsonSerializable()
class Data243 {
  const Data243({
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

  factory Data243.fromJson(Map<String, Object?> json) =>
      _$Data243FromJson(json);

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
  final Hall18? hall;
  final Table18? table;
  @JsonKey(name: 'hall_ref')
  final String? hallRef;
  @JsonKey(name: 'table_ref')
  final String? tableRef;
  @JsonKey(name: 'table_label')
  final String? tableLabel;
  @JsonKey(name: 'guests_count')
  final int? guestsCount;
  final Waiter21? waiter;
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
  final Pickup17? pickup;
  @JsonKey(name: 'total_price')
  final String totalPrice;
  final Charges21 charges;
  final Offline17? offline;
  final Queue19? queue;
  final int rounds;
  final List<Courses16> courses;
  @JsonKey(name: 'weight_pending')
  final int weightPending;
  final List<Items42> items;

  Map<String, Object?> toJson() => _$Data243ToJson(this);
}
