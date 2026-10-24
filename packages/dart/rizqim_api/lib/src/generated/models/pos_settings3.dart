// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'mode.dart';
import 'alerts3.dart';
import 'booking_confirmation3.dart';

part 'pos_settings3.g.dart';

@JsonSerializable()
class PosSettings3 {
  const PosSettings3({
    this.mode,
    this.printingDeviceId,
    this.screenLockMinutes,
    this.queueNumbers,
    this.cashierDiscountMaxPercent,
    this.alerts,
    this.bookingSlotMinutes,
    this.holidays,
    this.bookingConfirmation,
  });

  factory PosSettings3.fromJson(Map<String, Object?> json) =>
      _$PosSettings3FromJson(json);

  final Mode? mode;
  @JsonKey(name: 'printing_device_id')
  final String? printingDeviceId;
  @JsonKey(name: 'screen_lock_minutes')
  final int? screenLockMinutes;
  @JsonKey(name: 'queue_numbers')
  final bool? queueNumbers;
  @JsonKey(name: 'cashier_discount_max_percent')
  final num? cashierDiscountMaxPercent;
  final Alerts3? alerts;
  @JsonKey(name: 'booking_slot_minutes')
  final int? bookingSlotMinutes;
  final List<DateTime>? holidays;
  @JsonKey(name: 'booking_confirmation')
  final BookingConfirmation3? bookingConfirmation;

  Map<String, Object?> toJson() => _$PosSettings3ToJson(this);
}
