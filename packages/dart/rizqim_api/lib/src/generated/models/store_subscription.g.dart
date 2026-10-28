// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'store_subscription.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

StoreSubscription _$StoreSubscriptionFromJson(
  Map<String, dynamic> json,
) => StoreSubscription(
  id: (json['id'] as num).toInt(),
  storeId: (json['store_id'] as num).toInt(),
  tariffPlanId: (json['tariff_plan_id'] as num).toInt(),
  status: SubscriptionStatus.fromJson(json['status'] as String),
  startedAt: DateTime.parse(json['started_at'] as String),
  trialEndsAt: json['trial_ends_at'] == null
      ? null
      : DateTime.parse(json['trial_ends_at'] as String),
  currentPeriodStart: DateTime.parse(json['current_period_start'] as String),
  currentPeriodEnd: DateTime.parse(json['current_period_end'] as String),
  nextTariffPlanId: (json['next_tariff_plan_id'] as num?)?.toInt(),
  cancelAtPeriodEnd: json['cancel_at_period_end'] as bool,
  cancelledAt: json['cancelled_at'] == null
      ? null
      : DateTime.parse(json['cancelled_at'] as String),
  createdBy: json['created_by'],
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  billingPeriod: BillingPeriod.fromJson(json['billing_period'] as String),
  nextBillingPeriod: json['next_billing_period'] == null
      ? null
      : BillingPeriod.fromJson(json['next_billing_period'] as String),
  termEndsAt: json['term_ends_at'] == null
      ? null
      : DateTime.parse(json['term_ends_at'] as String),
  prorateFromPlanId: (json['prorate_from_plan_id'] as num?)?.toInt(),
  priceLockPlanId: (json['price_lock_plan_id'] as num?)?.toInt(),
  priceLockedUntil: json['price_locked_until'] == null
      ? null
      : DateTime.parse(json['price_locked_until'] as String),
  lockedAt: json['locked_at'] == null
      ? null
      : DateTime.parse(json['locked_at'] as String),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  plan: json['plan'] == null
      ? null
      : TariffPlan.fromJson(json['plan'] as Map<String, dynamic>),
  nextPlan: json['next_plan'] == null
      ? null
      : TariffPlan.fromJson(json['next_plan'] as Map<String, dynamic>),
  prorateFromPlan: json['prorate_from_plan'] == null
      ? null
      : TariffPlan.fromJson(json['prorate_from_plan'] as Map<String, dynamic>),
  priceLockPlan: json['price_lock_plan'] == null
      ? null
      : TariffPlan.fromJson(json['price_lock_plan'] as Map<String, dynamic>),
  invoices: (json['invoices'] as List<dynamic>?)
      ?.map((e) => SubscriptionInvoice.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$StoreSubscriptionToJson(StoreSubscription instance) =>
    <String, dynamic>{
      'id': instance.id,
      'store_id': instance.storeId,
      'tariff_plan_id': instance.tariffPlanId,
      'status': instance.status,
      'started_at': instance.startedAt.toIso8601String(),
      'trial_ends_at': instance.trialEndsAt?.toIso8601String(),
      'current_period_start': instance.currentPeriodStart.toIso8601String(),
      'current_period_end': instance.currentPeriodEnd.toIso8601String(),
      'next_tariff_plan_id': instance.nextTariffPlanId,
      'cancel_at_period_end': instance.cancelAtPeriodEnd,
      'cancelled_at': instance.cancelledAt?.toIso8601String(),
      'created_by': instance.createdBy,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'billing_period': instance.billingPeriod,
      'next_billing_period': instance.nextBillingPeriod,
      'term_ends_at': instance.termEndsAt?.toIso8601String(),
      'prorate_from_plan_id': instance.prorateFromPlanId,
      'price_lock_plan_id': instance.priceLockPlanId,
      'price_locked_until': instance.priceLockedUntil?.toIso8601String(),
      'locked_at': instance.lockedAt?.toIso8601String(),
      'store': instance.store,
      'plan': instance.plan,
      'next_plan': instance.nextPlan,
      'prorate_from_plan': instance.prorateFromPlan,
      'price_lock_plan': instance.priceLockPlan,
      'invoices': instance.invoices,
    };
