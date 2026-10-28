// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'plan3.dart';
import 'current_period2.dart';
import 'next_plan.dart';

part 'subscription.g.dart';

@JsonSerializable()
class Subscription {
  const Subscription({
    required this.status,
    required this.statusLabel,
    required this.plan,
    required this.startedAt,
    required this.trialEndsAt,
    required this.currentPeriod,
    required this.nextPlan,
    required this.cancelAtPeriodEnd,
    required this.billingPeriod,
    required this.nextBillingPeriod,
    required this.termEndsAt,
  });

  factory Subscription.fromJson(Map<String, Object?> json) =>
      _$SubscriptionFromJson(json);

  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  final Plan3 plan;
  @JsonKey(name: 'started_at')
  final String startedAt;
  @JsonKey(name: 'trial_ends_at')
  final String? trialEndsAt;
  @JsonKey(name: 'current_period')
  final CurrentPeriod2 currentPeriod;
  @JsonKey(name: 'next_plan')
  final NextPlan? nextPlan;
  @JsonKey(name: 'cancel_at_period_end')
  final bool cancelAtPeriodEnd;
  @JsonKey(name: 'billing_period')
  final String billingPeriod;
  @JsonKey(name: 'next_billing_period')
  final String? nextBillingPeriod;
  @JsonKey(name: 'term_ends_at')
  final String? termEndsAt;

  Map<String, Object?> toJson() => _$SubscriptionToJson(this);
}
