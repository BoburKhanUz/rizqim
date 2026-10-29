// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_pos_tips_distributions_response.dart';
import '../models/get_v1_owner_pos_tips_entries_response.dart';
import '../models/get_v1_owner_pos_tips_response.dart';
import '../models/get_v1_owner_pos_tips_settings_response.dart';
import '../models/post_v1_owner_pos_tips_distributions_response.dart';
import '../models/post_v1_owner_pos_tips_distributions_uuid_close_response.dart';
import '../models/post_v1_owner_pos_tips_uuid_corrections_response.dart';
import '../models/put_v1_owner_pos_tips_settings_response.dart';
import '../models/v1_owner_pos_tips_distributions_request_body.dart';
import '../models/v1_owner_pos_tips_settings_request_body.dart';
import '../models/v1_owner_pos_tips_uuid_corrections_request_body.dart';

part 'owner_tips_api.g.dart';

@RestApi()
abstract class OwnerTipsApi {
  factory OwnerTipsApi(Dio dio, {String? baseUrl}) = _OwnerTipsApi;

  /// The tips report.
  ///
  /// The branch's tips for business days `from`..`to` (at most 92 days; default: today's business day): `totals`.
  /// (count, amount, by method, personal, pool, unassigned personal tips, by person, corrections), `by_day` (each.
  /// day's amount, personal, pool, cash/card/qr, its pool splits and what of the pool is not split yet), `by_staff`.
  /// (personal tips, pool shares of the splits so far and of the closed ones, total). Tips are not revenue: they are.
  /// never in the sales reports.
  ///
  /// [branchId] - The branch (a branch-bound caller: their own when omitted).
  ///
  /// [from] - First business day (YYYY-MM-DD).
  ///
  /// [to] - Last business day (YYYY-MM-DD).
  @GET('/v1/owner/pos/tips')
  Future<GetV1OwnerPosTipsResponse> tipsReport({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// The pool splits.
  ///
  /// The branch's pool splits of business days `from`..`to`, each with its rule, pool, lines (person, role, basis —.
  /// 1, minutes, sales in so'm or the role weight — and amount in whole so'm) and status.
  ///
  /// [branchId] - The branch (a branch-bound caller: their own when omitted).
  ///
  /// [from] - First business day (YYYY-MM-DD).
  ///
  /// [to] - Last business day (YYYY-MM-DD).
  @GET('/v1/owner/pos/tips/distributions')
  Future<GetV1OwnerPosTipsDistributionsResponse> tipsDistributions({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// Compute the pool split.
  ///
  /// Splits the pool tips of `business_date` (or of the shift `shift_uuid`) by the branch's rule among the people of.
  /// its roles who worked then (clock-in sessions, the till shifts they opened, else the orders they served), or.
  /// among `participants` ([{staff_id, minutes?}], the manager's own list). Stored; computing again replaces it.
  /// until it is closed (409 `tips_distribution_closed`). Every basis 0: split equally. Shares are whole so'm: the.
  /// so'm left over go to the largest remainders, any tiyin to the largest share — the lines add up to the pool.
  /// tips.manage. Audited (pos.tips_distribution_computed).
  @POST('/v1/owner/pos/tips/distributions')
  Future<PostV1OwnerPosTipsDistributionsResponse> tipsCompute({
    @Body() required V1OwnerPosTipsDistributionsRequestBody body,
  });

  /// Close the pool split.
  ///
  /// Freezes the split: no more recomputing and no more corrections of the tips it covers. The pool changed since.
  /// it was computed (a tip came, a correction): 409 `tips_distribution_stale` (compute again first); already.
  /// closed: 409 `tips_distribution_closed`. tips.manage. Audited (pos.tips_distribution_closed).
  @POST('/v1/owner/pos/tips/distributions/{uuid}/close')
  Future<PostV1OwnerPosTipsDistributionsUuidCloseResponse> tipsClose({
    @Path('uuid') required String uuid,
  });

  /// The tips taken.
  ///
  /// Each tip of business days `from`..`to` (newest first, at most 500) with what it gives whom now, its corrections.
  /// and the bill, payment and shift it came with. `staff_id`: one person's (whom a tip goes to now). `closed`: a.
  /// closed pool split covers it (no more corrections).
  ///
  /// [branchId] - The branch (a branch-bound caller: their own when omitted).
  ///
  /// [from] - First business day (YYYY-MM-DD).
  ///
  /// [to] - Last business day (YYYY-MM-DD).
  ///
  /// [staffId] - Only the tips that go to this person now.
  @GET('/v1/owner/pos/tips/entries')
  Future<GetV1OwnerPosTipsEntriesResponse> tipsEntries({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('staff_id') int? staffId,
  });

  /// The branch's tip settings.
  ///
  /// `mode` personal (to the order's waiter, or the one picked at payment) or pool; `pool_rule` equal, hours, sales.
  /// or role_weights; `pool_period` day or shift; `roles` taking part in the pool; `role_weights` per role ("1.00".
  /// unless set). `enabled`: the branch has the `tips` module. `options`: the choices with their labels.
  ///
  /// [branchId] - The branch (a branch-bound caller: their own when omitted).
  @GET('/v1/owner/pos/tips/settings')
  Future<GetV1OwnerPosTipsSettingsResponse> tipsSettings({
    @Query('branch_id') int? branchId,
  });

  /// Change the branch's tip settings.
  ///
  /// Send what changes: `mode`, `pool_rule`, `pool_period`, `roles` (at least one; owner is never one), `role_weights`.
  /// ({role: 0.01-100}). Applies to tips taken from now on (a tip keeps the mode it was taken in). Needs the `tips`.
  /// module (403 module_disabled); a locked subscription changes nothing (403 subscription_locked). Audited.
  /// (settings.tips_changed).
  @PUT('/v1/owner/pos/tips/settings')
  Future<PutV1OwnerPosTipsSettingsResponse> tipsUpdateSettings({
    @Body() V1OwnerPosTipsSettingsRequestBody? body,
  });

  /// Correct a tip.
  ///
  /// A manager's correction (tips.manage) with a `reason`: the tip now gives `amount` (null: as now) to `mode`.
  /// personal (`staff_id`; null: unassigned) or pool. Written as signed rows next to the tip (the original stays);.
  /// a refund never does this by itself. 409 `tips_period_closed` when a closed pool split covers the tip; 422.
  /// `nothing_to_correct`, `reason_required`, `staff_not_found`. Audited (pos.tip_corrected).
  @POST('/v1/owner/pos/tips/{uuid}/corrections')
  Future<PostV1OwnerPosTipsUuidCorrectionsResponse> tipsCorrect({
    @Path('uuid') required String uuid,
    @Body() required V1OwnerPosTipsUuidCorrectionsRequestBody body,
  });
}
