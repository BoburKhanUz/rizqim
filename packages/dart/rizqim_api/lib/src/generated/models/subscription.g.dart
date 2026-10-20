// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'subscription.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Subscription _$SubscriptionFromJson(Map<String, dynamic> json) => Subscription(
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  plan: Plan2.fromJson(json['plan'] as Map<String, dynamic>),
  startedAt: json['started_at'] as String,
  trialEndsAt: json['trial_ends_at'] as String?,
  currentPeriod: CurrentPeriod.fromJson(
    json['current_period'] as Map<String, dynamic>,
  ),
  nextPlan: json['next_plan'] == null
      ? null
      : NextPlan.fromJson(json['next_plan'] as Map<String, dynamic>),
  cancelAtPeriodEnd: json['cancel_at_period_end'] as bool,
);

Map<String, dynamic> _$SubscriptionToJson(Subscription instance) =>
    <String, dynamic>{
      'status': instance.status,
      'status_label': instance.statusLabel,
      'plan': instance.plan,
      'started_at': instance.startedAt,
      'trial_ends_at': instance.trialEndsAt,
      'current_period': instance.currentPeriod,
      'next_plan': instance.nextPlan,
      'cancel_at_period_end': instance.cancelAtPeriodEnd,
    };
