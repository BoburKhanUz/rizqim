// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_branches_id_booking_rules_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerBranchesIdBookingRulesRequestBody
_$V1OwnerBranchesIdBookingRulesRequestBodyFromJson(
  Map<String, dynamic> json,
) => V1OwnerBranchesIdBookingRulesRequestBody(
  tableSlotMinutes: (json['table_slot_minutes'] as num?)?.toInt(),
  roomSlotMinutes: (json['room_slot_minutes'] as num?)?.toInt(),
  tapchanSlotMinutes: (json['tapchan_slot_minutes'] as num?)?.toInt(),
  tableMinAdvanceMinutes: (json['table_min_advance_minutes'] as num?)?.toInt(),
  roomMinAdvanceMinutes: (json['room_min_advance_minutes'] as num?)?.toInt(),
  tapchanMinAdvanceMinutes: (json['tapchan_min_advance_minutes'] as num?)
      ?.toInt(),
  maxAdvanceDays: (json['max_advance_days'] as num?)?.toInt(),
  depositMode: json['deposit_mode'] == null
      ? null
      : DepositMode.fromJson(json['deposit_mode'] as String),
  depositAmount: json['deposit_amount'] as num?,
  depositFromGuests: (json['deposit_from_guests'] as num?)?.toInt(),
  depositRequiredToConfirm: json['deposit_required_to_confirm'] as bool?,
);

Map<String, dynamic> _$V1OwnerBranchesIdBookingRulesRequestBodyToJson(
  V1OwnerBranchesIdBookingRulesRequestBody instance,
) => <String, dynamic>{
  'table_slot_minutes': instance.tableSlotMinutes,
  'room_slot_minutes': instance.roomSlotMinutes,
  'tapchan_slot_minutes': instance.tapchanSlotMinutes,
  'table_min_advance_minutes': instance.tableMinAdvanceMinutes,
  'room_min_advance_minutes': instance.roomMinAdvanceMinutes,
  'tapchan_min_advance_minutes': instance.tapchanMinAdvanceMinutes,
  'max_advance_days': instance.maxAdvanceDays,
  'deposit_mode': instance.depositMode,
  'deposit_amount': instance.depositAmount,
  'deposit_from_guests': instance.depositFromGuests,
  'deposit_required_to_confirm': instance.depositRequiredToConfirm,
};
