// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'customer2.dart';
import 'delivery2.dart';
import 'courier2.dart';
import 'items5.dart';
import 'status_history.dart';

part 'data82.g.dart';

@JsonSerializable()
class Data82 {
  const Data82({
    required this.subtotal,
    required this.shortCode,
    required this.status,
    required this.statusLabel,
    required this.type,
    required this.channel,
    required this.storeId,
    required this.branchId,
    required this.branchName,
    required this.paymentMethod,
    required this.paymentStatus,
    required this.uuid,
    required this.discount,
    required this.deliveryPrice,
    required this.serviceChargeAmount,
    required this.placeFeeAmount,
    required this.totalPrice,
    required this.notes,
    required this.guestsCount,
    required this.hallRef,
    required this.tableRef,
    required this.cancelReason,
    required this.cancelNote,
    required this.statusHistory,
    required this.delivery,
    required this.courier,
    required this.itemsTotal,
    required this.itemsReady,
    required this.items,
    required this.createdAt,
    required this.updatedAt,
    required this.acceptedAt,
    required this.preparingAt,
    required this.readyAt,
    required this.closedAt,
    required this.customer,
  });

  factory Data82.fromJson(Map<String, Object?> json) => _$Data82FromJson(json);

  final String uuid;
  @JsonKey(name: 'short_code')
  final String? shortCode;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  final String type;
  final String channel;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'branch_name')
  final String? branchName;
  @JsonKey(name: 'payment_method')
  final String paymentMethod;
  @JsonKey(name: 'payment_status')
  final String paymentStatus;
  final String subtotal;
  final String discount;
  @JsonKey(name: 'delivery_price')
  final String deliveryPrice;
  @JsonKey(name: 'service_charge_amount')
  final String serviceChargeAmount;
  @JsonKey(name: 'place_fee_amount')
  final String placeFeeAmount;
  @JsonKey(name: 'total_price')
  final String totalPrice;
  final String? notes;
  @JsonKey(name: 'guests_count')
  final int? guestsCount;
  @JsonKey(name: 'hall_ref')
  final String? hallRef;
  @JsonKey(name: 'table_ref')
  final String? tableRef;
  @JsonKey(name: 'cancel_reason')
  final String? cancelReason;
  @JsonKey(name: 'cancel_note')
  final String? cancelNote;
  final Customer2? customer;
  final Delivery2? delivery;
  final Courier2? courier;
  @JsonKey(name: 'items_total')
  final int itemsTotal;
  @JsonKey(name: 'items_ready')
  final int itemsReady;
  final List<Items5> items;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;
  @JsonKey(name: 'accepted_at')
  final String? acceptedAt;
  @JsonKey(name: 'preparing_at')
  final String? preparingAt;
  @JsonKey(name: 'ready_at')
  final String? readyAt;
  @JsonKey(name: 'closed_at')
  final String? closedAt;
  @JsonKey(name: 'status_history')
  final List<StatusHistory> statusHistory;

  Map<String, Object?> toJson() => _$Data82ToJson(this);
}
