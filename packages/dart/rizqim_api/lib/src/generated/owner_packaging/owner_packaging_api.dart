// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_branches_branch_packaging_rules_rule_response.dart';
import '../models/get_v1_owner_branches_branch_business_days_response.dart';
import '../models/get_v1_owner_branches_branch_packaging_rules_response.dart';
import '../models/get_v1_owner_branches_branch_pickup_settings_response.dart';
import '../models/post_v1_owner_branches_branch_packaging_rules_response.dart';
import '../models/put_v1_owner_branches_branch_packaging_rules_rule_response.dart';
import '../models/put_v1_owner_branches_branch_pickup_settings_response.dart';
import '../models/v1_owner_branches_branch_pickup_settings_request_body.dart';

part 'owner_packaging_api.g.dart';

@RestApi()
abstract class OwnerPackagingApi {
  factory OwnerPackagingApi(Dio dio, {String? baseUrl}) = _OwnerPackagingApi;

  /// Business days of a branch.
  ///
  /// `from` / `to` (YYYY-MM-DD business dates, at most 31 days; default: the last 7 days to today's business day).
  /// `orders`: POS orders by their business day, online ones by when they were placed (cancelled and rejected left.
  /// out); `revenue` the total of those not refunded; `after_midnight_orders`: opened after midnight (their calendar.
  /// date is the next day); `shifts`: opened on that business day (their Z is that day's, wherever it closed).
  ///
  /// [from] - YYYY-MM-DD, the first business day.
  ///
  /// [to] - YYYY-MM-DD, the last business day (at most 31 days after from).
  @GET('/v1/owner/branches/{branch}/business-days')
  Future<GetV1OwnerBranchesBranchBusinessDaysResponse>
  branchBusinessDayReportIndex({
    @Path('branch') required String branch,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// The branch's packaging rules.
  ///
  /// In their order. `basis` (a PackagingBasis value): `order` — `quantity` per order; `item` — `quantity` per portion.
  /// of the dishes of `category_id` or `product_id`. `order_types`: PackagingOrderType values. `mxik` / `package_code`.
  /// null: the store's fiscal "packaging" code (fiscal settings).
  @GET('/v1/owner/branches/{branch}/packaging-rules')
  Future<GetV1OwnerBranchesBranchPackagingRulesResponse>
  branchPackagingRulesIndex({@Path('branch') required String branch});

  /// Add a packaging rule.
  ///
  /// At most 50 rules per branch (422). An `item` rule names exactly one of `category_id` and `product_id` (the.
  /// store's); an `order` rule none. Audited.
  @POST('/v1/owner/branches/{branch}/packaging-rules')
  Future<PostV1OwnerBranchesBranchPackagingRulesResponse>
  branchPackagingRulesStore({@Path('branch') required String branch});

  /// Change a packaging rule.
  ///
  /// Send only what changes. Audited.
  @PUT('/v1/owner/branches/{branch}/packaging-rules/{rule}')
  Future<PutV1OwnerBranchesBranchPackagingRulesRuleResponse>
  branchPackagingRulesUpdate({
    @Path('branch') required String branch,
    @Path('rule') required String rule,
  });

  /// Delete a packaging rule.
  ///
  /// The orders that already have its line keep it as it was (their snapshot); open orders lose it on their next.
  /// recalculation. Audited.
  @DELETE('/v1/owner/branches/{branch}/packaging-rules/{rule}')
  Future<DeleteV1OwnerBranchesBranchPackagingRulesRuleResponse>
  branchPackagingRulesDestroy({
    @Path('branch') required String branch,
    @Path('rule') required String rule,
  });

  /// The branch's pickup-time rules.
  ///
  /// Every key with its default when nothing was saved (`configured: false`): `pickup_min_lead_minutes` (20),.
  /// `pickup_slot_minutes` (15), `pickup_max_days_ahead` (1; 0: today's business day only), `pickup_prep_minutes` (15:.
  /// the kitchen gets the order this long before the pickup time, or the longest `wait_minutes` of its dishes).
  /// `round_the_clock`: the 24/7 module (times at any hour); `opening_hours`: the store's hours the times keep to.
  @GET('/v1/owner/branches/{branch}/pickup-settings')
  Future<GetV1OwnerBranchesBranchPickupSettingsResponse>
  branchPickupSettingsShow({@Path('branch') required String branch});

  /// Change the branch's pickup-time rules.
  ///
  /// Send only what changes. Audited.
  @PUT('/v1/owner/branches/{branch}/pickup-settings')
  Future<PutV1OwnerBranchesBranchPickupSettingsResponse>
  branchPickupSettingsUpdate({
    @Path('branch') required String branch,
    @Body() V1OwnerBranchesBranchPickupSettingsRequestBody? body,
  });
}
