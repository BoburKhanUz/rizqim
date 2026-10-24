// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'alerts.dart';

part 'pos_settings.g.dart';

@JsonSerializable()
class PosSettings {
  const PosSettings({
    required this.mode,
    required this.printingDeviceId,
    required this.screenLockMinutes,
    required this.queueNumbers,
    required this.cashierDiscountMaxPercent,
    required this.alerts,
    required this.holidays,
    required this.bookingSlotMinutes,
  });

  factory PosSettings.fromJson(Map<String, Object?> json) =>
      _$PosSettingsFromJson(json);

  final String mode;
  @JsonKey(name: 'printing_device_id')
  final String? printingDeviceId;
  @JsonKey(name: 'screen_lock_minutes')
  final int screenLockMinutes;
  @JsonKey(name: 'queue_numbers')
  final bool queueNumbers;
  @JsonKey(name: 'cashier_discount_max_percent')
  final String? cashierDiscountMaxPercent;
  final Alerts alerts;
  final List<String> holidays;
  @JsonKey(name: 'booking_slot_minutes')
  final int? bookingSlotMinutes;

  Map<String, Object?> toJson() => _$PosSettingsToJson(this);
}
