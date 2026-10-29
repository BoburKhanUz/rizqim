// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_billing_entitlements_response.dart';
import '../models/get_v1_owner_invoices_response.dart';
import '../models/get_v1_owner_subscription_response.dart';
import '../models/subscription_invoice_status.dart';

part 'owner_billing_api.g.dart';

@RestApi()
abstract class OwnerBillingApi {
  factory OwnerBillingApi(Dio dio, {String? baseUrl}) = _OwnerBillingApi;

  /// What the plan lets the store use.
  ///
  /// P4-21 (docs/BILLING.md, "Funksiyalar va cheklovlar"): `plan` (null without a subscription), `legacy` (true: every.
  /// feature, the store is not on a v2 plan yet), `period` monthly|yearly, `status` none|trialing|active|past_due|locked,.
  /// `trial_ends_at`, `current_period`, `term_ends_at` (yearly), `locked_since`, `lock_at` (when an overdue invoice locks.
  /// it), `settings_locked`, `features` (every App\Enums\PlanFeature: on or off), `required_plans` (the cheapest plan.
  /// with each gated feature: show "Asosiy tarifda bor"), `devices` (pos and kds: included, active, extra, extra_price.
  /// as a decimal string, can_add; waiter phones and customer displays unlimited and free), `notice` (the overdue invoice reminder or the lock.
  /// message for a banner, null when none; its `invoice` — uuid, number, amounts — only for a member with finance.view on.
  /// their own sign-in, null for other staff and terminal PIN sessions, whose `message` does not name it either). Any.
  /// member of the store and the terminals read it.
  @GET('/v1/owner/billing/entitlements')
  Future<GetV1OwnerBillingEntitlementsResponse> ownerBillingEntitlements();

  /// The store's subscription invoices, newest month first.
  ///
  /// [page] - P2-30: the Laravel page (documented, so the generated clients carry it).
  @GET('/v1/owner/invoices')
  Future<GetV1OwnerInvoicesResponse> ownerBillingInvoices({
    @Query('status') SubscriptionInvoiceStatus? status,
    @Query('period') String? period,
    @Query('per_page') int? perPage,
    @Query('page') int? page,
  });

  /// The current plan and period, the terminals counted so far and an estimate of the period's invoice.
  /// `subscription` is null for a store on no plan (commission at the store's own rate, no monthly fee).
  @GET('/v1/owner/subscription')
  Future<GetV1OwnerSubscriptionResponse> ownerBillingSubscription();
}
