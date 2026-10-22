// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'branch.dart';
import 'cash_movement.dart';
import 'device.dart';
import 'shift_status.dart';
import 'store.dart';

part 'pos_shift.g.dart';

@JsonSerializable()
class PosShift {
  const PosShift({
    required this.id,
    required this.uuid,
    required this.storeId,
    required this.branchId,
    required this.deviceId,
    required this.businessDate,
    required this.status,
    required this.openedBy,
    required this.openedAt,
    required this.openingCash,
    required this.closedBy,
    required this.closedAt,
    required this.expectedCash,
    required this.countedCash,
    required this.countedBreakdown,
    required this.difference,
    required this.zNumber,
    required this.forceClosed,
    required this.closeNote,
    required this.forcedBy,
    required this.summary,
    required this.createdAt,
    required this.updatedAt,
    this.store,
    this.branch,
    this.device,
    this.cashMovements,
  });

  factory PosShift.fromJson(Map<String, Object?> json) =>
      _$PosShiftFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'device_id')
  final int? deviceId;
  @JsonKey(name: 'business_date')
  final DateTime businessDate;
  final ShiftStatus status;
  @JsonKey(name: 'opened_by')
  final dynamic openedBy;
  @JsonKey(name: 'opened_at')
  final DateTime openedAt;
  @JsonKey(name: 'opening_cash')
  final String openingCash;
  @JsonKey(name: 'closed_by')
  final dynamic closedBy;
  @JsonKey(name: 'closed_at')
  final DateTime? closedAt;
  @JsonKey(name: 'expected_cash')
  final String? expectedCash;
  @JsonKey(name: 'counted_cash')
  final String? countedCash;
  @JsonKey(name: 'counted_breakdown')
  final Map<String, int>? countedBreakdown;
  final String? difference;
  @JsonKey(name: 'z_number')
  final int? zNumber;
  @JsonKey(name: 'force_closed')
  final bool forceClosed;
  @JsonKey(name: 'close_note')
  final String? closeNote;
  @JsonKey(name: 'forced_by')
  final int? forcedBy;
  final Map<String, dynamic>? summary;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final Store? store;
  final Branch? branch;
  final Device? device;
  @JsonKey(name: 'cash_movements')
  final List<CashMovement>? cashMovements;

  Map<String, Object?> toJson() => _$PosShiftToJson(this);
}
