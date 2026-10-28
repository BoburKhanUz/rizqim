// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data35.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data35 _$Data35FromJson(Map<String, dynamic> json) => Data35(
  plan: json['plan'] == null
      ? null
      : Plan.fromJson(json['plan'] as Map<String, dynamic>),
  legacy: json['legacy'] as bool,
  period: json['period'] as String?,
  status: json['status'] as String,
  trialEndsAt: json['trial_ends_at'] as String?,
  currentPeriod: json['current_period'] == null
      ? null
      : CurrentPeriod.fromJson(json['current_period'] as Map<String, dynamic>),
  termEndsAt: json['term_ends_at'] as String?,
  lockedSince: json['locked_since'] as String?,
  lockAt: json['lock_at'] as String?,
  settingsLocked: json['settings_locked'] as bool,
  features: Map<String, bool>.from(json['features'] as Map),
  requiredPlans: Map<String, String?>.from(json['required_plans'] as Map),
  devices: Devices.fromJson(json['devices'] as Map<String, dynamic>),
  notice: json['notice'] == null
      ? null
      : Notice.fromJson(json['notice'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data35ToJson(Data35 instance) => <String, dynamic>{
  'plan': instance.plan,
  'legacy': instance.legacy,
  'period': instance.period,
  'status': instance.status,
  'trial_ends_at': instance.trialEndsAt,
  'current_period': instance.currentPeriod,
  'term_ends_at': instance.termEndsAt,
  'locked_since': instance.lockedSince,
  'lock_at': instance.lockAt,
  'settings_locked': instance.settingsLocked,
  'features': instance.features,
  'required_plans': instance.requiredPlans,
  'devices': instance.devices,
  'notice': instance.notice,
};
