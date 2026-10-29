// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_batch_plans_id_response.dart';
import '../models/get_v1_owner_batch_plans_response.dart';
import '../models/get_v1_owner_batch_writeoffs_response.dart';
import '../models/get_v1_owner_batch_yield_response.dart';
import '../models/get_v1_owner_batches_response.dart';
import '../models/post_v1_owner_batch_plans_response.dart';
import '../models/post_v1_owner_batches_id_adjust_response.dart';
import '../models/post_v1_owner_batches_id_close_response.dart';
import '../models/post_v1_owner_batches_id_write_off_response.dart';
import '../models/post_v1_owner_batches_response.dart';
import '../models/v1_owner_batch_plans_request_body.dart';
import '../models/v1_owner_batches_id_adjust_request_body.dart';
import '../models/v1_owner_batches_id_write_off_request_body.dart';
import '../models/v1_owner_batches_request_body.dart';

part 'owner_batches_api.g.dart';

@RestApi()
abstract class OwnerBatchesApi {
  factory OwnerBatchesApi(Dio dio, {String? baseUrl}) = _OwnerBatchesApi;

  /// A branch's planned batches.
  ///
  /// P6-15, module `batches`: the "keyingi partiya HH:MM" plans still `planned` (a plan whose time has passed is.
  /// `overdue` and no longer shown to guests), earliest first, and `modules` (`batches`, `batch_writeoff`: which of the.
  /// batch features the branch's format has, so a screen shows the write-off button only where it works). Branch-bound.
  /// staff always get their own branch.
  ///
  /// [branchId] - Required unless the caller is bound to a branch.
  @GET('/v1/owner/batch-plans')
  Future<GetV1OwnerBatchPlansResponse> batchPlanIndex({
    @Query('branch_id') int? branchId,
  });

  /// Plan the next batch.
  ///
  /// `time` is "HH:MM" today in the store's time zone (it must not have passed), or `planned_at` an ISO moment (at most.
  /// 36 hours ahead). `product_variant_id` null: the plan serves every variant. Where the dish is "tugadi", the till and.
  /// the Mini App then show "keyingi partiya 12:30". Opening a batch of that dish finishes the plan.
  @POST('/v1/owner/batch-plans')
  Future<PostV1OwnerBatchPlansResponse> batchPlanStore({
    @Body() required V1OwnerBatchPlansRequestBody body,
  });

  /// Cancel a planned batch.
  ///
  /// The time is no longer shown to guests. A plan already started (its batch opened) stays as it is.
  @DELETE('/v1/owner/batch-plans/{id}')
  Future<DeleteV1OwnerBatchPlansIdResponse> batchPlanDestroy({
    @Path('id') required int id,
  });

  /// Written-off portions.
  ///
  /// P6-15, module `batch_writeoff`: the branch's write-offs (newest first) between two business days (`from`, `to`,.
  /// YYYY-MM-DD; default today), with who wrote off, why and on which shift. `reasons` is the list of reasons with.
  /// their Uzbek labels for a picker. Needs batches.manage or reports.view.
  ///
  /// [branchId] - Required unless the caller is bound to a branch.
  ///
  /// [from] - First business day, YYYY-MM-DD (default: today).
  ///
  /// [to] - Last business day, YYYY-MM-DD (default: from).
  @GET('/v1/owner/batch-writeoffs')
  Future<GetV1OwnerBatchWriteoffsResponse> batchWriteOffIndex({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// Yield report (hosil hisoboti).
  ///
  /// P6-15, module `batches`: for the batches started between two business days (`from`, `to`, YYYY-MM-DD; default.
  /// today; at most 93 days) what was cooked (`produced`), sold, written off, what is left and the unexplained.
  /// `variance`, per batch (`batches`) and per day and dish (`days`); `write_offs` adds the written-off portions by.
  /// reason and `in_sets` the portions of dishes sold inside a kompleks. Quantities are numbers in the batch's unit.
  /// (portions, kg); percents are strings. Needs reports.view or batches.manage.
  ///
  /// [branchId] - Required unless the caller is bound to a branch.
  ///
  /// [from] - First business day, YYYY-MM-DD (default: today).
  ///
  /// [to] - Last business day, YYYY-MM-DD (default: from).
  @GET('/v1/owner/batch-yield')
  Future<GetV1OwnerBatchYieldResponse> batchYieldShow({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// A branch's batches.
  ///
  /// Open batches first (with what is left), then the latest closed ones. Branch-bound staff always get their own.
  /// branch.
  ///
  /// [branchId] - Required unless the caller is bound to a branch.
  ///
  /// [includeClosed] - Also the last 50 closed batches.
  @GET('/v1/owner/batches')
  Future<GetV1OwnerBatchesResponse> batchIndex({
    @Query('branch_id') int? branchId,
    @Query('include_closed') bool? includeClosed,
  });

  /// Open a batch.
  ///
  /// What the branch cooked of a product (`product_variant_id` null: the batch serves every variant, each using its.
  /// `batch_consumption` per unit, e.g. 0.7 for a "0.7 porsiya"). One open batch per branch and product/variant: 422.
  /// otherwise.
  @POST('/v1/owner/batches')
  Future<PostV1OwnerBatchesResponse> batchStore({
    @Body() required V1OwnerBatchesRequestBody body,
  });

  /// Top up or correct a batch.
  ///
  /// `add`: more was cooked (produced and left both grow). `quantity_left`: set what is left (a recount). One of.
  /// them is required.
  @POST('/v1/owner/batches/{id}/adjust')
  Future<PostV1OwnerBatchesIdAdjustResponse> batchAdjust({
    @Path('id') required int id,
    @Body() V1OwnerBatchesIdAdjustRequestBody? body,
  });

  /// Close a batch.
  ///
  /// A batch-tracked product is "tugadi" on the branch until a new batch opens. Closing a closed batch is a no-op.
  @POST('/v1/owner/batches/{id}/close')
  Future<PostV1OwnerBatchesIdCloseResponse> batchClose({
    @Path('id') required int id,
  });

  /// Write off unsold portions.
  ///
  /// P6-15, module `batch_writeoff`: takes `quantity` off what the batch still holds, with a `reason` (unsold, spoiled,.
  /// staff_meal, dropped, expired, other; `other` needs a `note`). More than what is left: 422. `close` also closes.
  /// the batch (the end-of-day "write off the rest"). Done from a terminal, it belongs to that terminal's open shift.
  /// and is listed on its Z report; from the panel it appears in the reports only. Send an Idempotency-Key so a retry.
  /// does not write the same portions off twice. Audited (`menu.batch_written_off`).
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/owner/batches/{id}/write-off')
  Future<PostV1OwnerBatchesIdWriteOffResponse> batchWriteOff({
    @Path('id') required int id,
    @Body() required V1OwnerBatchesIdWriteOffRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });
}
