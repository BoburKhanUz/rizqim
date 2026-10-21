// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'alerts2.dart';

part 'pos_settings2.g.dart';

@JsonSerializable()
class PosSettings2 {
  const PosSettings2({
    required this.mode,
    required this.printingDeviceId,
    required this.screenLockMinutes,
    required this.queueNumbers,
    required this.alerts,
    required this.holidays,
    required this.bookingSlotMinutes,
  });

  factory PosSettings2.fromJson(Map<String, Object?> json) =>
      _$PosSettings2FromJson(json);

  final String mode;
  @JsonKey(name: 'printing_device_id')
  final String? printingDeviceId;
  @JsonKey(name: 'screen_lock_minutes')
  final int screenLockMinutes;
  @JsonKey(name: 'queue_numbers')
  final bool queueNumbers;
  final Alerts2 alerts;
  final List<String> holidays;
  @JsonKey(name: 'booking_slot_minutes')
  final int? bookingSlotMinutes;

  Map<String, Object?> toJson() => _$PosSettings2ToJson(this);
}
