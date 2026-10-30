// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'booking_rules.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

BookingRules _$BookingRulesFromJson(Map<String, dynamic> json) => BookingRules(
  branchId: (json['branch_id'] as num).toInt(),
  configured: json['configured'] as bool,
  tableSlotMinutes: (json['table_slot_minutes'] as num).toInt(),
  roomSlotMinutes: (json['room_slot_minutes'] as num?)?.toInt(),
  tapchanSlotMinutes: (json['tapchan_slot_minutes'] as num?)?.toInt(),
  tableMinAdvanceMinutes: (json['table_min_advance_minutes'] as num).toInt(),
  roomMinAdvanceMinutes: (json['room_min_advance_minutes'] as num).toInt(),
  tapchanMinAdvanceMinutes: (json['tapchan_min_advance_minutes'] as num)
      .toInt(),
  maxAdvanceDays: (json['max_advance_days'] as num?)?.toInt(),
  depositMode: json['deposit_mode'] as String,
  depositModeLabel: json['deposit_mode_label'] as String,
  depositAmount: json['deposit_amount'] as String,
  depositFromGuests: (json['deposit_from_guests'] as num).toInt(),
  depositRequiredToConfirm: json['deposit_required_to_confirm'] as bool,
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$BookingRulesToJson(BookingRules instance) =>
    <String, dynamic>{
      'branch_id': instance.branchId,
      'configured': instance.configured,
      'table_slot_minutes': instance.tableSlotMinutes,
      'room_slot_minutes': instance.roomSlotMinutes,
      'tapchan_slot_minutes': instance.tapchanSlotMinutes,
      'table_min_advance_minutes': instance.tableMinAdvanceMinutes,
      'room_min_advance_minutes': instance.roomMinAdvanceMinutes,
      'tapchan_min_advance_minutes': instance.tapchanMinAdvanceMinutes,
      'max_advance_days': instance.maxAdvanceDays,
      'deposit_mode': instance.depositMode,
      'deposit_mode_label': instance.depositModeLabel,
      'deposit_amount': instance.depositAmount,
      'deposit_from_guests': instance.depositFromGuests,
      'deposit_required_to_confirm': instance.depositRequiredToConfirm,
      'updated_at': instance.updatedAt,
    };
