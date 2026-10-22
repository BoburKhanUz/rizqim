// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'pos_settings2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PosSettings2 _$PosSettings2FromJson(Map<String, dynamic> json) => PosSettings2(
  mode: json['mode'] as String,
  printingDeviceId: json['printing_device_id'] as String?,
  screenLockMinutes: (json['screen_lock_minutes'] as num).toInt(),
  queueNumbers: json['queue_numbers'] as bool,
  alerts: Alerts2.fromJson(json['alerts'] as Map<String, dynamic>),
  holidays: (json['holidays'] as List<dynamic>)
      .map((e) => e as String)
      .toList(),
  bookingSlotMinutes: (json['booking_slot_minutes'] as num?)?.toInt(),
);

Map<String, dynamic> _$PosSettings2ToJson(PosSettings2 instance) =>
    <String, dynamic>{
      'mode': instance.mode,
      'printing_device_id': instance.printingDeviceId,
      'screen_lock_minutes': instance.screenLockMinutes,
      'queue_numbers': instance.queueNumbers,
      'alerts': instance.alerts,
      'holidays': instance.holidays,
      'booking_slot_minutes': instance.bookingSlotMinutes,
    };
