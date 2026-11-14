// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots4.dart';

part 'data147.g.dart';

@JsonSerializable()
class Data147 {
  const Data147({
    required this.slots,
    required this.holdHours,
    required this.guestWarningThresholds,
    required this.windowStart,
    required this.windowEnd,
    required this.advanceMxik,
    required this.advancePackageCode,
    required this.cancellationMxik,
    required this.cancellationPackageCode,
    required this.quoteCurrency,
    required this.version,
  });

  factory Data147.fromJson(Map<String, Object?> json) =>
      _$Data147FromJson(json);

  final Slots4 slots;
  @JsonKey(name: 'hold_hours')
  final int holdHours;
  @JsonKey(name: 'guest_warning_thresholds')
  final List<int> guestWarningThresholds;
  @JsonKey(name: 'window_start')
  final String windowStart;
  @JsonKey(name: 'window_end')
  final String windowEnd;
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
  final int version;

  Map<String, Object?> toJson() => _$Data147ToJson(this);
}
