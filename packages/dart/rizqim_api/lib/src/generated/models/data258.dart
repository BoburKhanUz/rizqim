// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items52.dart';
import 'pickup_status.dart';

part 'data258.g.dart';

@JsonSerializable()
class Data258 {
  const Data258({
    required this.uuid,
    required this.shortCode,
    required this.status,
    required this.type,
    required this.channel,
    required this.branchId,
    required this.hallRef,
    required this.tableRef,
    required this.tableLabel,
    required this.guestsCount,
    required this.notes,
    required this.itemsTotal,
    required this.itemsReady,
    required this.items,
    required this.createdAt,
    required this.acceptedAt,
    required this.preparingAt,
    required this.readyAt,
    required this.canceledAt,
    required this.waitingSeconds,
    required this.queueNumber,
    required this.pickupStatus,
  });

  factory Data258.fromJson(Map<String, Object?> json) =>
      _$Data258FromJson(json);

  final String uuid;
  @JsonKey(name: 'short_code')
  final String? shortCode;
  final String status;
  final String type;
  final String channel;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  @JsonKey(name: 'hall_ref')
  final String? hallRef;
  @JsonKey(name: 'table_ref')
  final String? tableRef;
  @JsonKey(name: 'table_label')
  final String? tableLabel;
  @JsonKey(name: 'guests_count')
  final int? guestsCount;
  final String? notes;
  @JsonKey(name: 'items_total')
  final int itemsTotal;
  @JsonKey(name: 'items_ready')
  final int itemsReady;
  final List<Items52> items;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'accepted_at')
  final String? acceptedAt;
  @JsonKey(name: 'preparing_at')
  final String? preparingAt;
  @JsonKey(name: 'ready_at')
  final String? readyAt;
  @JsonKey(name: 'canceled_at')
  final String? canceledAt;
  @JsonKey(name: 'waiting_seconds')
  final int waitingSeconds;
  @JsonKey(name: 'queue_number')
  final int? queueNumber;
  @JsonKey(name: 'pickup_status')
  final PickupStatus? pickupStatus;

  Map<String, Object?> toJson() => _$Data258ToJson(this);
}
