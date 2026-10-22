// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'customer.dart';
import 'delivery4.dart';
import 'courier.dart';
import 'items19.dart';

part 'online.g.dart';

@JsonSerializable()
class Online {
  const Online({
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
    required this.waitingSeconds,
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

  factory Online.fromJson(Map<String, Object?> json) => _$OnlineFromJson(json);

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
  final Customer? customer;
  final Delivery4? delivery;
  final Courier? courier;
  @JsonKey(name: 'items_total')
  final int itemsTotal;
  @JsonKey(name: 'items_ready')
  final int itemsReady;
  final List<Items19> items;
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
  @JsonKey(name: 'waiting_seconds')
  final int waitingSeconds;

  Map<String, Object?> toJson() => _$OnlineToJson(this);
}
