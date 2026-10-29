// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'plan.dart';
import 'current_period.dart';
import 'devices.dart';
import 'notice.dart';

part 'data39.g.dart';

@JsonSerializable()
class Data39 {
  const Data39({
    required this.plan,
    required this.legacy,
    required this.period,
    required this.status,
    required this.trialEndsAt,
    required this.currentPeriod,
    required this.termEndsAt,
    required this.lockedSince,
    required this.lockAt,
    required this.settingsLocked,
    required this.features,
    required this.requiredPlans,
    required this.devices,
    required this.notice,
  });

  factory Data39.fromJson(Map<String, Object?> json) => _$Data39FromJson(json);

  final Plan? plan;
  final bool legacy;
  final String? period;
  final String status;
  @JsonKey(name: 'trial_ends_at')
  final String? trialEndsAt;
  @JsonKey(name: 'current_period')
  final CurrentPeriod? currentPeriod;
  @JsonKey(name: 'term_ends_at')
  final String? termEndsAt;
  @JsonKey(name: 'locked_since')
  final String? lockedSince;
  @JsonKey(name: 'lock_at')
  final String? lockAt;
  @JsonKey(name: 'settings_locked')
  final bool settingsLocked;
  final Map<String, bool> features;
  @JsonKey(name: 'required_plans')
  final Map<String, String?> requiredPlans;
  final Devices devices;
  final Notice? notice;

  Map<String, Object?> toJson() => _$Data39ToJson(this);
}
