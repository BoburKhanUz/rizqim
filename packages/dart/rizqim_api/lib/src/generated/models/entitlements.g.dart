// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'entitlements.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Entitlements _$EntitlementsFromJson(Map<String, dynamic> json) => Entitlements(
  plan: json['plan'] == null
      ? null
      : Plan4.fromJson(json['plan'] as Map<String, dynamic>),
  legacy: json['legacy'] as bool,
  period: json['period'] as String?,
  status: json['status'] as String,
  trialEndsAt: json['trial_ends_at'] as String?,
  currentPeriod: json['current_period'] == null
      ? null
      : CurrentPeriod3.fromJson(json['current_period'] as Map<String, dynamic>),
  termEndsAt: json['term_ends_at'] as String?,
  lockedSince: json['locked_since'] as String?,
  lockAt: json['lock_at'] as String?,
  settingsLocked: json['settings_locked'] as bool,
  features: Map<String, bool>.from(json['features'] as Map),
  requiredPlans: Map<String, String?>.from(json['required_plans'] as Map),
  devices: (json['devices'] as Map<String, dynamic>).map(
    (k, e) => MapEntry(k, e as Map<String, dynamic>),
  ),
  notice: json['notice'] as Map<String, dynamic>?,
);

Map<String, dynamic> _$EntitlementsToJson(Entitlements instance) =>
    <String, dynamic>{
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
