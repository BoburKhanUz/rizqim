// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch.dart';
import 'dining_table_kind.dart';
import 'hall.dart';
import 'min_spend_mode.dart';
import 'order.dart';
import 'store.dart';

part 'dining_table.g.dart';

@JsonSerializable()
class DiningTable {
  const DiningTable({
    required this.id,
    required this.storeId,
    required this.branchId,
    required this.hallId,
    required this.name,
    required this.kind,
    required this.seats,
    required this.layoutX,
    required this.layoutY,
    required this.layoutW,
    required this.layoutH,
    required this.sortOrder,
    required this.isActive,
    required this.minSpend,
    required this.minSpendMode,
    required this.createdAt,
    required this.updatedAt,
    required this.deletedAt,
    required this.serviceState,
    required this.serviceStateAt,
    required this.serviceStateBy,
    this.store,
    this.branch,
    this.hall,
    this.openOrders,
  });

  factory DiningTable.fromJson(Map<String, Object?> json) =>
      _$DiningTableFromJson(json);

  final int id;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'hall_id')
  final int hallId;
  final String name;
  final DiningTableKind kind;
  final int? seats;
  @JsonKey(name: 'layout_x')
  final String? layoutX;
  @JsonKey(name: 'layout_y')
  final String? layoutY;
  @JsonKey(name: 'layout_w')
  final String? layoutW;
  @JsonKey(name: 'layout_h')
  final String? layoutH;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'min_spend')
  final String? minSpend;
  @JsonKey(name: 'min_spend_mode')
  final MinSpendMode minSpendMode;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'deleted_at')
  final DateTime? deletedAt;
  @JsonKey(name: 'service_state')
  final String? serviceState;
  @JsonKey(name: 'service_state_at')
  final DateTime? serviceStateAt;
  @JsonKey(name: 'service_state_by')
  final int? serviceStateBy;
  final Store? store;
  final Branch? branch;
  final Hall? hall;
  @JsonKey(name: 'open_orders')
  final List<Order>? openOrders;

  Map<String, Object?> toJson() => _$DiningTableToJson(this);
}
