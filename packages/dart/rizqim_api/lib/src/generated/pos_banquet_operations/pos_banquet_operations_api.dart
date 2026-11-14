// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_banquets_osh_calculator_response.dart';
import '../models/get_v1_pos_banquets_shopping_list_response.dart';
import '../models/get_v1_pos_banquets_uuid_contract_response.dart';
import '../models/get_v1_pos_banquets_uuid_production_sheet_response.dart';
import '../models/get_v1_pos_banquets_uuid_settlement_response.dart';
import '../models/get_v1_pos_banquets_uuid_staff_response.dart';
import '../models/get_v1_pos_banquets_uuid_warnings_response.dart';
import '../models/patch_v1_pos_banquets_uuid_guests_response.dart';
import '../models/post_v1_pos_banquets_uuid_contract_number_response.dart';
import '../models/post_v1_pos_banquets_uuid_settle_response.dart';
import '../models/post_v1_pos_banquets_uuid_settlement_refund_excess_response.dart';
import '../models/post_v1_pos_banquets_uuid_start_response.dart';
import '../models/put_v1_pos_banquets_uuid_staff_response.dart';
import '../models/v1_pos_banquets_uuid_guests_request_body.dart';
import '../models/v1_pos_banquets_uuid_settle_request_body.dart';
import '../models/v1_pos_banquets_uuid_settlement_refund_excess_request_body.dart';
import '../models/v1_pos_banquets_uuid_staff_request_body.dart';
import '../models/v1_pos_banquets_uuid_start_request_body.dart';

part 'pos_banquet_operations_api.g.dart';

@RestApi()
abstract class PosBanquetOperationsApi {
  factory PosBanquetOperationsApi(Dio dio, {String? baseUrl}) =
      _PosBanquetOperationsApi;

  /// Osh calculator.
  ///
  /// For `persons` guests (1–5000): kg of osh, the ingredients by the store's ratios and the kazans. banquets.manage,.
  /// pos.take_payment, reports.view or kitchen.manage.
  ///
  /// [persons] - How many guests (1–5000).
  @GET('/v1/pos/banquets/osh-calculator')
  Future<GetV1PosBanquetsOshCalculatorResponse> banquetOpsOsh({
    @Query('persons') required int persons,
  });

  /// Shopping list (bozorlik) of a date range.
  ///
  /// The branch's events on `from`..`to` (the store's dates, at most 62 days; default today) that hold their halls.
  /// (hold, confirmed, in progress): their dishes summed by product and unit, the osh ingredients summed, each event's.
  /// planned guests. No customer data. banquets.manage, pos.take_payment, reports.view or kitchen.manage.
  ///
  /// [branchId] - The branch (default: the one the caller works in).
  ///
  /// [from] - First day, Y-m-d (store time zone).
  ///
  /// [to] - Last day, Y-m-d, inclusive.
  @GET('/v1/pos/banquets/shopping-list')
  Future<GetV1PosBanquetsShoppingListResponse> banquetOpsShopping({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// Shopping list as Excel.
  ///
  /// [branchId] - The branch (default: the one the caller works in).
  ///
  /// [from] - First day, Y-m-d (store time zone).
  ///
  /// [to] - Last day, Y-m-d, inclusive.
  @GET('/v1/pos/banquets/shopping-list/export')
  @DioResponseType(ResponseType.stream)
  Stream<String> banquetOpsShoppingExport({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// Contract (shartnoma).
  ///
  /// The store's contract template filled from the event: `text` (and `lines`) to print, the `fields` used, the.
  /// warnings. `contract_no` is null until POST .../contract/number. banquets.manage.
  @GET('/v1/pos/banquets/{uuid}/contract')
  Future<GetV1PosBanquetsUuidContractResponse> banquetOpsContract({
    @Path('uuid') required String uuid,
  });

  /// Give the contract its number.
  ///
  /// "<year>-<seq>" per store and year, once (the same number again on a retry). Audited. banquets.manage.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/banquets/{uuid}/contract/number')
  Future<PostV1PosBanquetsUuidContractNumberResponse> banquetOpsNumberContract({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Set the guests who came.
  ///
  /// `guests_actual` (0–100000), optional `version`. The final bill uses max(actual, guaranteed, minimum). Audited.
  /// banquets.manage.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @PATCH('/v1/pos/banquets/{uuid}/guests')
  Future<PatchV1PosBanquetsUuidGuestsResponse> banquetOpsGuests({
    @Path('uuid') required String uuid,
    @Body() required V1PosBanquetsUuidGuestsRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Production sheet of an event.
  ///
  /// The dishes of the event's package × the planned guests (max of actual, guaranteed, minimum, plus the store's.
  /// reserve %), in portions and kg by kitchen station, the osh calculator's result (kazans and ingredients) and the.
  /// event's warnings. No customer data. banquets.manage, pos.take_payment, reports.view or kitchen.manage.
  @GET('/v1/pos/banquets/{uuid}/production-sheet')
  Future<GetV1PosBanquetsUuidProductionSheetResponse> banquetOpsProduction({
    @Path('uuid') required String uuid,
  });

  /// Production sheet as Excel
  @GET('/v1/pos/banquets/{uuid}/production-sheet/export')
  @DioResponseType(ResponseType.stream)
  Stream<String> banquetOpsProductionExport({
    @Path('uuid') required String uuid,
  });

  /// Settle the event.
  ///
  /// At the till: the package, hall rent and extras join the event's order as its `banquet` charge, the order is billed.
  /// as one bill and the paid advances are offset on it (prepayment, in the open shift: `shift_uuid` or the.
  /// terminal's). The answer carries the `bill`: pay what is left on POST /pos/bills/{uuid}/payments with any tender;.
  /// once it is covered ONE sale receipt is issued for the full total (the advances as its prepayment part), the order.
  /// closes and the event becomes `settled`. Advances over the total are refunded against their advance receipts (cash.
  /// from the drawer needs pos.refund or a manager's `approval_token`; else `excess_status` stays `pending` — POST.
  /// .../settlement/refund-excess). Retrying before money is on the bill recomputes it. 409 `invalid_transition`,.
  /// `order_closed`, `shift_not_open`; 422 `mxik_missing`. pos.take_payment.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/banquets/{uuid}/settle')
  Future<PostV1PosBanquetsUuidSettleResponse> banquetOpsSettle({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() V1PosBanquetsUuidSettleRequestBody? body,
  });

  /// Final settlement (preview).
  ///
  /// What the final bill is now: the billed guests, the package, hall and extras lines, the event-day order, the.
  /// total, the advances paid (and offset), what is left to pay or to give back; with the settlement once made.
  /// banquets.manage or pos.take_payment.
  @GET('/v1/pos/banquets/{uuid}/settlement')
  Future<GetV1PosBanquetsUuidSettlementResponse> banquetOpsSettlement({
    @Path('uuid') required String uuid,
  });

  /// Refund the advances over the total.
  ///
  /// After the final bill is covered: what the advances hold beyond it goes back against their advance receipts,.
  /// newest first (cash from the open shift's drawer: pos.refund or a manager's `approval_token`). pos.take_payment.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/banquets/{uuid}/settlement/refund-excess')
  Future<PostV1PosBanquetsUuidSettlementRefundExcessResponse>
  banquetOpsRefundExcess({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() V1PosBanquetsUuidSettlementRefundExcessRequestBody? body,
  });

  /// Staff assigned to the event
  @GET('/v1/pos/banquets/{uuid}/staff')
  Future<GetV1PosBanquetsUuidStaffResponse> banquetOpsStaff({
    @Path('uuid') required String uuid,
  });

  /// Assign the event's staff.
  ///
  /// `user_ids`: the waiters who serve the event (staff of the store, up to 100; an empty list clears it). A settled.
  /// event counts once for each of them in the staff pay rules' event rate (kind `banquet`). Audited. banquets.manage.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @PUT('/v1/pos/banquets/{uuid}/staff')
  Future<PutV1PosBanquetsUuidStaffResponse> banquetOpsUpdateStaff({
    @Path('uuid') required String uuid,
    @Body() required V1PosBanquetsUuidStaffRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Start the event day.
  ///
  /// A confirmed event opens its tab: a hall order bound to the event (`order_uuid`; drinks and extra dishes are its.
  /// ordinary rounds) and becomes `in_progress`. Optional `waiter_id`, `version`. Again: the same tab. 409.
  /// `invalid_transition` (not confirmed), `stale_version`. banquets.manage or pos.take_payment.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/banquets/{uuid}/start')
  Future<PostV1PosBanquetsUuidStartResponse> banquetOpsStart({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') String? idempotencyKey,
    @Body() V1PosBanquetsUuidStartRequestBody? body,
  });

  /// Event warnings.
  ///
  /// Guests over the store's thresholds, a time outside the window: warnings only, never a refusal.
  @GET('/v1/pos/banquets/{uuid}/warnings')
  Future<GetV1PosBanquetsUuidWarningsResponse> banquetOpsWarnings({
    @Path('uuid') required String uuid,
  });
}
