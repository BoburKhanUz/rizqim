// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data53.g.dart';

@JsonSerializable()
class Data53 {
  const Data53({
    required this.branchId,
    required this.configured,
    required this.tableSlotMinutes,
    required this.roomSlotMinutes,
    required this.tapchanSlotMinutes,
    required this.tableMinAdvanceMinutes,
    required this.roomMinAdvanceMinutes,
    required this.tapchanMinAdvanceMinutes,
    required this.maxAdvanceDays,
    required this.depositMode,
    required this.depositModeLabel,
    required this.depositAmount,
    required this.depositFromGuests,
    required this.depositRequiredToConfirm,
    required this.updatedAt,
  });

  factory Data53.fromJson(Map<String, Object?> json) => _$Data53FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool configured;
  @JsonKey(name: 'table_slot_minutes')
  final int tableSlotMinutes;
  @JsonKey(name: 'room_slot_minutes')
  final int? roomSlotMinutes;
  @JsonKey(name: 'tapchan_slot_minutes')
  final int? tapchanSlotMinutes;
  @JsonKey(name: 'table_min_advance_minutes')
  final int tableMinAdvanceMinutes;
  @JsonKey(name: 'room_min_advance_minutes')
  final int roomMinAdvanceMinutes;
  @JsonKey(name: 'tapchan_min_advance_minutes')
  final int tapchanMinAdvanceMinutes;
  @JsonKey(name: 'max_advance_days')
  final int? maxAdvanceDays;
  @JsonKey(name: 'deposit_mode')
  final String depositMode;
  @JsonKey(name: 'deposit_mode_label')
  final String depositModeLabel;
  @JsonKey(name: 'deposit_amount')
  final String depositAmount;
  @JsonKey(name: 'deposit_from_guests')
  final int depositFromGuests;
  @JsonKey(name: 'deposit_required_to_confirm')
  final bool depositRequiredToConfirm;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data53ToJson(this);
}
