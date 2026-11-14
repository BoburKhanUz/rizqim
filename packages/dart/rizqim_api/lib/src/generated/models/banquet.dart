// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots9.dart';

part 'banquet.g.dart';

@JsonSerializable()
class Banquet {
  const Banquet({
    required this.slots,
    required this.holdHours,
    required this.guestWarningThresholds,
    required this.windowStart,
    required this.windowEnd,
    required this.quoteCurrency,
  });

  factory Banquet.fromJson(Map<String, Object?> json) =>
      _$BanquetFromJson(json);

  final Slots9 slots;
  @JsonKey(name: 'hold_hours')
  final int holdHours;
  @JsonKey(name: 'guest_warning_thresholds')
  final List<int> guestWarningThresholds;
  @JsonKey(name: 'window_start')
  final String windowStart;
  @JsonKey(name: 'window_end')
  final String windowEnd;
  @JsonKey(name: 'quote_currency')
  final String? quoteCurrency;

  Map<String, Object?> toJson() => _$BanquetToJson(this);
}
