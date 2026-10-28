// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'subscription.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Subscription _$SubscriptionFromJson(Map<String, dynamic> json) => Subscription(
  status: json['status'] as String,
  statusLabel: json['status_label'] as String,
  plan: Plan3.fromJson(json['plan'] as Map<String, dynamic>),
  startedAt: json['started_at'] as String,
  trialEndsAt: json['trial_ends_at'] as String?,
  currentPeriod: CurrentPeriod2.fromJson(
    json['current_period'] as Map<String, dynamic>,
  ),
  nextPlan: json['next_plan'] == null
      ? null
      : NextPlan.fromJson(json['next_plan'] as Map<String, dynamic>),
  cancelAtPeriodEnd: json['cancel_at_period_end'] as bool,
  billingPeriod: json['billing_period'] as String,
  nextBillingPeriod: json['next_billing_period'] as String?,
  termEndsAt: json['term_ends_at'] as String?,
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
      'billing_period': instance.billingPeriod,
      'next_billing_period': instance.nextBillingPeriod,
      'term_ends_at': instance.termEndsAt,
    };
