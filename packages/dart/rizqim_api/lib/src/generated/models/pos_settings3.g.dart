// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'pos_settings3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PosSettings3 _$PosSettings3FromJson(Map<String, dynamic> json) => PosSettings3(
  mode: json['mode'] == null ? null : Mode.fromJson(json['mode'] as String),
  printingDeviceId: json['printing_device_id'] as String?,
  screenLockMinutes: (json['screen_lock_minutes'] as num?)?.toInt(),
  queueNumbers: json['queue_numbers'] as bool?,
  cashierDiscountMaxPercent: json['cashier_discount_max_percent'] as num?,
  alerts: json['alerts'] == null
      ? null
      : Alerts3.fromJson(json['alerts'] as Map<String, dynamic>),
  bookingSlotMinutes: (json['booking_slot_minutes'] as num?)?.toInt(),
  holidays: (json['holidays'] as List<dynamic>?)
      ?.map((e) => DateTime.parse(e as String))
      .toList(),
  bookingConfirmation: json['booking_confirmation'] == null
      ? null
      : BookingConfirmation3.fromJson(
          json['booking_confirmation'] as Map<String, dynamic>,
        ),
);

Map<String, dynamic> _$PosSettings3ToJson(PosSettings3 instance) =>
    <String, dynamic>{
      'mode': instance.mode,
      'printing_device_id': instance.printingDeviceId,
      'screen_lock_minutes': instance.screenLockMinutes,
      'queue_numbers': instance.queueNumbers,
      'cashier_discount_max_percent': instance.cashierDiscountMaxPercent,
      'alerts': instance.alerts,
      'booking_slot_minutes': instance.bookingSlotMinutes,
      'holidays': instance.holidays?.map((e) => e.toIso8601String()).toList(),
      'booking_confirmation': instance.bookingConfirmation,
    };
