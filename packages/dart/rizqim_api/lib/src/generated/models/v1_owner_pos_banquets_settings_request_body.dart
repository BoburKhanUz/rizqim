// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots6.dart';

part 'v1_owner_pos_banquets_settings_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosBanquetsSettingsRequestBody {
  const V1OwnerPosBanquetsSettingsRequestBody({
    this.slots,
    this.holdHours,
    this.guestWarningThresholds,
    this.windowStart,
    this.windowEnd,
    this.advanceMxik,
    this.advancePackageCode,
    this.cancellationMxik,
    this.cancellationPackageCode,
    this.quoteCurrency,
  });

  factory V1OwnerPosBanquetsSettingsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosBanquetsSettingsRequestBodyFromJson(json);

  final List<Slots6>? slots;
  @JsonKey(name: 'hold_hours')
  final int? holdHours;
  @JsonKey(name: 'guest_warning_thresholds')
  final List<int>? guestWarningThresholds;
  @JsonKey(name: 'window_start')
  final String? windowStart;
  @JsonKey(name: 'window_end')
  final String? windowEnd;
  @JsonKey(name: 'advance_mxik')
  final String? advanceMxik;
  @JsonKey(name: 'advance_package_code')
  final String? advancePackageCode;
  @JsonKey(name: 'cancellation_mxik')
  final String? cancellationMxik;
  @JsonKey(name: 'cancellation_package_code')
  final String? cancellationPackageCode;
  @JsonKey(name: 'quote_currency')
  final String? quoteCurrency;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosBanquetsSettingsRequestBodyToJson(this);
}
