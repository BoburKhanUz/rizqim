// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'settings6.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Settings6 _$Settings6FromJson(Map<String, dynamic> json) => Settings6(
  slots: Slots8.fromJson(json['slots'] as Map<String, dynamic>),
  holdHours: (json['hold_hours'] as num).toInt(),
  guestWarningThresholds: (json['guest_warning_thresholds'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  windowStart: json['window_start'] as String,
  windowEnd: json['window_end'] as String,
  advanceMxik: json['advance_mxik'] as String?,
  advancePackageCode: json['advance_package_code'] as String?,
  cancellationMxik: json['cancellation_mxik'] as String?,
  cancellationPackageCode: json['cancellation_package_code'] as String?,
  quoteCurrency: json['quote_currency'] as String?,
  version: (json['version'] as num).toInt(),
);

Map<String, dynamic> _$Settings6ToJson(Settings6 instance) => <String, dynamic>{
  'slots': instance.slots,
  'hold_hours': instance.holdHours,
  'guest_warning_thresholds': instance.guestWarningThresholds,
  'window_start': instance.windowStart,
  'window_end': instance.windowEnd,
  'advance_mxik': instance.advanceMxik,
  'advance_package_code': instance.advancePackageCode,
  'cancellation_mxik': instance.cancellationMxik,
  'cancellation_package_code': instance.cancellationPackageCode,
  'quote_currency': instance.quoteCurrency,
  'version': instance.version,
};
