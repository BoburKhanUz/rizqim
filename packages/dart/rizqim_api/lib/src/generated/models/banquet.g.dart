// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'banquet.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Banquet _$BanquetFromJson(Map<String, dynamic> json) => Banquet(
  slots: Slots9.fromJson(json['slots'] as Map<String, dynamic>),
  holdHours: (json['hold_hours'] as num).toInt(),
  guestWarningThresholds: (json['guest_warning_thresholds'] as List<dynamic>)
      .map((e) => (e as num).toInt())
      .toList(),
  windowStart: json['window_start'] as String,
  windowEnd: json['window_end'] as String,
  quoteCurrency: json['quote_currency'] as String?,
);

Map<String, dynamic> _$BanquetToJson(Banquet instance) => <String, dynamic>{
  'slots': instance.slots,
  'hold_hours': instance.holdHours,
  'guest_warning_thresholds': instance.guestWarningThresholds,
  'window_start': instance.windowStart,
  'window_end': instance.windowEnd,
  'quote_currency': instance.quoteCurrency,
};
