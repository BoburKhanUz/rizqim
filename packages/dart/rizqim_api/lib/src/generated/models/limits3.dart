// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'limits3.g.dart';

@JsonSerializable()
class Limits3 {
  const Limits3({
    required this.batchSize,
    required this.warnAfterMinutes,
    required this.alertAfterMinutes,
    required this.maxQueuedOrders,
    required this.menuMaxAgeHours,
    required this.contactMaxAgeHours,
    required this.fiscalWarnHours,
    required this.fiscalLimitHours,
    required this.clockSkewWarnSeconds,
  });

  factory Limits3.fromJson(Map<String, Object?> json) =>
      _$Limits3FromJson(json);

  @JsonKey(name: 'batch_size')
  final int batchSize;
  @JsonKey(name: 'warn_after_minutes')
  final int warnAfterMinutes;
  @JsonKey(name: 'alert_after_minutes')
  final int alertAfterMinutes;
  @JsonKey(name: 'max_queued_orders')
  final int maxQueuedOrders;
  @JsonKey(name: 'menu_max_age_hours')
  final int menuMaxAgeHours;
  @JsonKey(name: 'contact_max_age_hours')
  final int contactMaxAgeHours;
  @JsonKey(name: 'fiscal_warn_hours')
  final List<int> fiscalWarnHours;
  @JsonKey(name: 'fiscal_limit_hours')
  final int fiscalLimitHours;
  @JsonKey(name: 'clock_skew_warn_seconds')
  final int clockSkewWarnSeconds;

  Map<String, Object?> toJson() => _$Limits3ToJson(this);
}
