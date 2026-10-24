// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'alerts4.dart';
import 'booking_confirmation4.dart';

part 'pos_settings4.g.dart';

@JsonSerializable()
class PosSettings4 {
  const PosSettings4({
    required this.mode,
    required this.printingDeviceId,
    required this.screenLockMinutes,
    required this.queueNumbers,
    required this.cashierDiscountMaxPercent,
    required this.alerts,
    required this.holidays,
    required this.bookingSlotMinutes,
    required this.bookingConfirmation,
  });

  factory PosSettings4.fromJson(Map<String, Object?> json) =>
      _$PosSettings4FromJson(json);

  final String mode;
  @JsonKey(name: 'printing_device_id')
  final String? printingDeviceId;
  @JsonKey(name: 'screen_lock_minutes')
  final int screenLockMinutes;
  @JsonKey(name: 'queue_numbers')
  final bool queueNumbers;
  @JsonKey(name: 'cashier_discount_max_percent')
  final String? cashierDiscountMaxPercent;
  final Alerts4 alerts;
  final List<String> holidays;
  @JsonKey(name: 'booking_slot_minutes')
  final int? bookingSlotMinutes;
  @JsonKey(name: 'booking_confirmation')
  final BookingConfirmation4 bookingConfirmation;

  Map<String, Object?> toJson() => _$PosSettings4ToJson(this);
}
