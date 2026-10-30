// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_pos_service_charge_rules_response.dart';
import '../models/put_v1_owner_pos_service_charge_rules_response.dart';
import '../models/v1_owner_pos_service_charge_rules_request_body.dart';

part 'owner_service_charge_rules_api.g.dart';

@RestApi()
abstract class OwnerServiceChargeRulesApi {
  factory OwnerServiceChargeRulesApi(Dio dio, {String? baseUrl}) =
      _OwnerServiceChargeRulesApi;

  /// The branch's service charge rules.
  ///
  /// The rows in force in the branch: the store's default (`branch_id` null), the branch's own and its halls' own,.
  /// each with its base percent, its `rules` (`by_order_type` {hall|table_service|takeaway: percent},.
  /// `large_group_min_guests` with `large_group_percent`, `cover_amount` per guest), the owner's `notice` and.
  /// `notice_preview` (the text the bill prints). `enabled`: the branch has the `service_charge_rules` module;.
  /// `locked`: a stolovaya (0%, VM 75).
  ///
  /// [branchId] - The branch (a branch-bound caller: their own when omitted).
  @GET('/v1/owner/pos/service-charge-rules')
  Future<GetV1OwnerPosServiceChargeRulesResponse> serviceChargeRulesShow({
    @Query('branch_id') int? branchId,
  });

  /// Set the service charge rules.
  ///
  /// For the branch (or, with `hall_id`, one of its halls). Send the whole set: `by_order_type` ({hall,.
  /// table_service, takeaway}: percent 0-30; a type left out keeps the row's own percent), `large_group_min_guests`.
  /// (2-500) with `large_group_percent` (0-30) — both or neither, `cover_amount` (per guest, 0 or null: none),.
  /// `notice` (up to 300 characters; null: worked out). A row the branch or hall did not have yet is made from the.
  /// one in force there (the base percent and place fee unchanged). A stolovaya takes no positive percent (422.
  /// service_charge_locked); without the `service_charge_rules` module: 403 module_disabled. Audited.
  /// (settings.service_charge_rules_changed).
  @PUT('/v1/owner/pos/service-charge-rules')
  Future<PutV1OwnerPosServiceChargeRulesResponse> serviceChargeRulesUpdate({
    @Body() V1OwnerPosServiceChargeRulesRequestBody? body,
  });
}
