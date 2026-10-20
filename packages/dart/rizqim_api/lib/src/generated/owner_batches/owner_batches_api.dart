// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_batches_response.dart';
import '../models/post_v1_owner_batches_id_adjust_response.dart';
import '../models/post_v1_owner_batches_id_close_response.dart';
import '../models/post_v1_owner_batches_response.dart';
import '../models/v1_owner_batches_id_adjust_request_body.dart';
import '../models/v1_owner_batches_request_body.dart';

part 'owner_batches_api.g.dart';

@RestApi()
abstract class OwnerBatchesApi {
  factory OwnerBatchesApi(Dio dio, {String? baseUrl}) = _OwnerBatchesApi;

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
}
