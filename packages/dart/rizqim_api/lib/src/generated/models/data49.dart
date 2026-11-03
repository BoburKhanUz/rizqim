// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'opening_hours2.dart';

part 'data49.g.dart';

@JsonSerializable()
class Data49 {
  const Data49({
    required this.branchId,
    required this.configured,
    required this.pickupMinLeadMinutes,
    required this.pickupSlotMinutes,
    required this.pickupMaxDaysAhead,
    required this.pickupPrepMinutes,
    required this.roundTheClock,
    required this.openingHours,
    required this.updatedAt,
  });

  factory Data49.fromJson(Map<String, Object?> json) => _$Data49FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final bool configured;
  @JsonKey(name: 'pickup_min_lead_minutes')
  final int pickupMinLeadMinutes;
  @JsonKey(name: 'pickup_slot_minutes')
  final int pickupSlotMinutes;
  @JsonKey(name: 'pickup_max_days_ahead')
  final int pickupMaxDaysAhead;
  @JsonKey(name: 'pickup_prep_minutes')
  final int pickupPrepMinutes;
  @JsonKey(name: 'round_the_clock')
  final bool roundTheClock;
  @JsonKey(name: 'opening_hours')
  final OpeningHours2 openingHours;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data49ToJson(this);
}
