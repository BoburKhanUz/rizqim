// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'hall4.dart';
import 'table4.dart';
import 'waiter7.dart';
import 'queue5.dart';
import 'online2.dart';

part 'data176.g.dart';

@JsonSerializable()
class Data176 {
  const Data176({
    required this.uuid,
    required this.shortCode,
    required this.status,
    required this.channel,
    required this.type,
    required this.branchId,
    required this.version,
    required this.hall,
    required this.table,
    required this.hallRef,
    required this.tableRef,
    required this.guestsCount,
    required this.waiter,
    required this.itemsCount,
    required this.subtotal,
    required this.totalPrice,
    required this.paymentStatus,
    required this.billLocked,
    required this.billRequested,
    required this.fiscalPending,
    required this.bookingId,
    required this.businessDate,
    required this.queue,
    required this.openedAt,
    required this.closedAt,
    required this.updatedAt,
    required this.online,
  });

  factory Data176.fromJson(Map<String, Object?> json) =>
      _$Data176FromJson(json);

  final String uuid;
  @JsonKey(name: 'short_code')
  final String? shortCode;
  final String status;
  final String channel;
  final String type;
  @JsonKey(name: 'branch_id')
  final int branchId;
  final int version;
  final Hall4? hall;
  final Table4? table;
  @JsonKey(name: 'hall_ref')
  final String? hallRef;
  @JsonKey(name: 'table_ref')
  final String? tableRef;
  @JsonKey(name: 'guests_count')
  final int? guestsCount;
  final Waiter7? waiter;
  @JsonKey(name: 'items_count')
  final int itemsCount;
  final String subtotal;
  @JsonKey(name: 'total_price')
  final String totalPrice;
  @JsonKey(name: 'payment_status')
  final String paymentStatus;
  @JsonKey(name: 'bill_locked')
  final bool billLocked;
  @JsonKey(name: 'bill_requested')
  final bool billRequested;
  @JsonKey(name: 'fiscal_pending')
  final bool fiscalPending;
  @JsonKey(name: 'booking_id')
  final int? bookingId;
  @JsonKey(name: 'business_date')
  final String? businessDate;
  final Queue5? queue;
  @JsonKey(name: 'opened_at')
  final String? openedAt;
  @JsonKey(name: 'closed_at')
  final String? closedAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;
  final Online2? online;

  Map<String, Object?> toJson() => _$Data176ToJson(this);
}
