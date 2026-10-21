// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'pos_settings4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PosSettings4 _$PosSettings4FromJson(Map<String, dynamic> json) => PosSettings4(
  mode: json['mode'] as String,
  printingDeviceId: json['printing_device_id'] as String?,
  screenLockMinutes: (json['screen_lock_minutes'] as num).toInt(),
  queueNumbers: json['queue_numbers'] as bool,
  alerts: Alerts4.fromJson(json['alerts'] as Map<String, dynamic>),
  holidays: (json['holidays'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  bookingSlotMinutes: (json['booking_slot_minutes'] as num?)?.toInt(),
);

Map<String, dynamic> _$PosSettings4ToJson(PosSettings4 instance) =>
    <String, dynamic>{
      'mode': instance.mode,
      'printing_device_id': instance.printingDeviceId,
      'screen_lock_minutes': instance.screenLockMinutes,
      'queue_numbers': instance.queueNumbers,
      'alerts': instance.alerts,
      'holidays': instance.holidays,
      'booking_slot_minutes': instance.bookingSlotMinutes,
    };
