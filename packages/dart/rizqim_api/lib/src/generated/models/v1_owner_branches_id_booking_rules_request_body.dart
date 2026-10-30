// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'deposit_mode.dart';

part 'v1_owner_branches_id_booking_rules_request_body.g.dart';

@JsonSerializable()
class V1OwnerBranchesIdBookingRulesRequestBody {
  const V1OwnerBranchesIdBookingRulesRequestBody({
    this.tableSlotMinutes,
    this.roomSlotMinutes,
    this.tapchanSlotMinutes,
    this.tableMinAdvanceMinutes,
    this.roomMinAdvanceMinutes,
    this.tapchanMinAdvanceMinutes,
    this.maxAdvanceDays,
    this.depositMode,
    this.depositAmount,
    this.depositFromGuests,
    this.depositRequiredToConfirm,
  });

  factory V1OwnerBranchesIdBookingRulesRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerBranchesIdBookingRulesRequestBodyFromJson(json);

  @JsonKey(name: 'table_slot_minutes')
  final int? tableSlotMinutes;
  @JsonKey(name: 'room_slot_minutes')
  final int? roomSlotMinutes;
  @JsonKey(name: 'tapchan_slot_minutes')
  final int? tapchanSlotMinutes;
  @JsonKey(name: 'table_min_advance_minutes')
  final int? tableMinAdvanceMinutes;
  @JsonKey(name: 'room_min_advance_minutes')
  final int? roomMinAdvanceMinutes;
  @JsonKey(name: 'tapchan_min_advance_minutes')
  final int? tapchanMinAdvanceMinutes;
  @JsonKey(name: 'max_advance_days')
  final int? maxAdvanceDays;
  @JsonKey(name: 'deposit_mode')
  final DepositMode? depositMode;
  @JsonKey(name: 'deposit_amount')
  final num? depositAmount;
  @JsonKey(name: 'deposit_from_guests')
  final int? depositFromGuests;
  @JsonKey(name: 'deposit_required_to_confirm')
  final bool? depositRequiredToConfirm;

  Map<String, Object?> toJson() =>
      _$V1OwnerBranchesIdBookingRulesRequestBodyToJson(this);
}
