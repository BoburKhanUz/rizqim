// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_banquets_settings_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosBanquetsSettingsRequestBody
_$V1OwnerPosBanquetsSettingsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerPosBanquetsSettingsRequestBody(
      slots: (json['slots'] as List<dynamic>?)
          ?.map((e) => Slots6.fromJson(e as Map<String, dynamic>))
          .toList(),
      holdHours: (json['hold_hours'] as num?)?.toInt(),
      guestWarningThresholds:
          (json['guest_warning_thresholds'] as List<dynamic>?)
              ?.map((e) => (e as num).toInt())
              .toList(),
      windowStart: json['window_start'] as String?,
      windowEnd: json['window_end'] as String?,
      advanceMxik: json['advance_mxik'] as String?,
      advancePackageCode: json['advance_package_code'] as String?,
      cancellationMxik: json['cancellation_mxik'] as String?,
      cancellationPackageCode: json['cancellation_package_code'] as String?,
      quoteCurrency: json['quote_currency'] as String?,
    );

Map<String, dynamic> _$V1OwnerPosBanquetsSettingsRequestBodyToJson(
  V1OwnerPosBanquetsSettingsRequestBody instance,
) => <String, dynamic>{
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
};
