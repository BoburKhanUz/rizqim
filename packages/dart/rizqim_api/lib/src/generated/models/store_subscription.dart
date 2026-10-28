// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'billing_period.dart';
import 'store.dart';
import 'subscription_invoice.dart';
import 'subscription_status.dart';
import 'tariff_plan.dart';

part 'store_subscription.g.dart';

@JsonSerializable()
class StoreSubscription {
  const StoreSubscription({
    required this.id,
    required this.storeId,
    required this.tariffPlanId,
    required this.status,
    required this.startedAt,
    required this.trialEndsAt,
    required this.currentPeriodStart,
    required this.currentPeriodEnd,
    required this.nextTariffPlanId,
    required this.cancelAtPeriodEnd,
    required this.cancelledAt,
    required this.createdBy,
    required this.createdAt,
    required this.updatedAt,
    required this.billingPeriod,
    required this.nextBillingPeriod,
    required this.termEndsAt,
    required this.prorateFromPlanId,
    required this.priceLockPlanId,
    required this.priceLockedUntil,
    required this.lockedAt,
    this.store,
    this.plan,
    this.nextPlan,
    this.prorateFromPlan,
    this.priceLockPlan,
    this.invoices,
  });

  factory StoreSubscription.fromJson(Map<String, Object?> json) =>
      _$StoreSubscriptionFromJson(json);

  final int id;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'tariff_plan_id')
  final int tariffPlanId;
  final SubscriptionStatus status;
  @JsonKey(name: 'started_at')
  final DateTime startedAt;
  @JsonKey(name: 'trial_ends_at')
  final DateTime? trialEndsAt;
  @JsonKey(name: 'current_period_start')
  final DateTime currentPeriodStart;
  @JsonKey(name: 'current_period_end')
  final DateTime currentPeriodEnd;
  @JsonKey(name: 'next_tariff_plan_id')
  final int? nextTariffPlanId;
  @JsonKey(name: 'cancel_at_period_end')
  final bool cancelAtPeriodEnd;
  @JsonKey(name: 'cancelled_at')
  final DateTime? cancelledAt;
  @JsonKey(name: 'created_by')
  final dynamic createdBy;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'billing_period')
  final BillingPeriod billingPeriod;
  @JsonKey(name: 'next_billing_period')
  final BillingPeriod? nextBillingPeriod;
  @JsonKey(name: 'term_ends_at')
  final DateTime? termEndsAt;
  @JsonKey(name: 'prorate_from_plan_id')
  final int? prorateFromPlanId;
  @JsonKey(name: 'price_lock_plan_id')
  final int? priceLockPlanId;
  @JsonKey(name: 'price_locked_until')
  final DateTime? priceLockedUntil;
  @JsonKey(name: 'locked_at')
  final DateTime? lockedAt;
  final Store? store;
  final TariffPlan? plan;
  @JsonKey(name: 'next_plan')
  final TariffPlan? nextPlan;
  @JsonKey(name: 'prorate_from_plan')
  final TariffPlan? prorateFromPlan;
  @JsonKey(name: 'price_lock_plan')
  final TariffPlan? priceLockPlan;
  final List<SubscriptionInvoice>? invoices;

  Map<String, Object?> toJson() => _$StoreSubscriptionToJson(this);
}
