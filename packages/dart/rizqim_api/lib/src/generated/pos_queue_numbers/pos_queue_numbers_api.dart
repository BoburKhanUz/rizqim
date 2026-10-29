// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_queue_response.dart';
import '../models/post_v1_pos_orders_uuid_call_response.dart';
import '../models/post_v1_pos_orders_uuid_handed_response.dart';
import '../models/post_v1_pos_orders_uuid_ready_response.dart';
import '../models/post_v1_pos_queue_blocks_response.dart';

part 'pos_queue_numbers_api.g.dart';

@RestApi()
abstract class PosQueueNumbersApi {
  factory PosQueueNumbersApi(Dio dio, {String? baseUrl}) = _PosQueueNumbersApi;

  /// Call a ready number again.
  ///
  /// Re-announces a ready number: `queue.ready` with `called: true` (the customer display and the till's speaker.
  /// call it again), `call_count` + 1, `called_at`; an online customer gets the message again for the first 3 calls.
  /// (later calls are announced on the board only). 422 `call_too_soon` with `data.retry_in` (seconds) within 15 s.
  /// of the last call, `not_ready` unless it is ready, `not_in_queue`. Needs the `queue_calling` module and.
  /// orders.manage or kitchen.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/call')
  Future<PostV1PosOrdersUuidCallResponse> posQueueCall({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
  });

  /// Mark a number handed over.
  ///
  /// The customer took the order: `queue.handed`, the number leaves the board; a POS order's lines are served (its.
  /// bill and payment are not touched), an online pickup order is delivered. Already handed: unchanged. Works for.
  /// an order already in the queue after `queue_calling` was switched off; one not in it: 403 `module_disabled`.
  /// without the module, else 422 `not_in_queue`. Needs orders.manage or kitchen.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/handed')
  Future<PostV1PosOrdersUuidHandedResponse> posQueueHanded({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
  });

  /// Mark a number ready.
  ///
  /// The order is ready for the customer (a counter without a kitchen display, or the cashier overriding it):.
  /// `queue.ready` on `pos-branch.{id}`, an online pickup order becomes `ready` and its customer is told "Buyurtmangiz.
  ///      * tayyor, raqam N". Already ready: unchanged. A paid order of a branch that calls no numbers is handed at once.
  /// (`handed`). An order already in the queue is finished even after `queue_calling` was switched off; one not in it:.
  /// 403 `module_disabled` without the module, else 422 `not_in_queue` (no number, not a counter order);.
  /// 422 `already_handed`. Needs orders.manage or kitchen.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/ready')
  Future<PostV1PosOrdersUuidReadyResponse> posQueueReady({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
  });

  /// The pickup board.
  ///
  /// Today's numbers of the branch (its business day) still waiting: `preparing` (oldest first) and `ready` (the.
  /// longest waiting first), at most 200 each: the customer display's "Tayyorlanmoqda / Tayyor" and the till's.
  /// calling list. Refetch on `queue.*` events of `pos-branch.{id}`. Needs orders.view; `branch_id` defaults to the.
  /// caller's (or their terminal's) branch.
  ///
  /// [branchId] - The branch; default: the caller's (or their terminal's).
  @GET('/v1/pos/queue')
  Future<GetV1PosQueueResponse> posQueueBoard({
    @Query('branch_id') int? branchId,
  });

  /// Reserve the till's next number block.
  ///
  /// The till asks for its next block of numbers while online, when it holds fewer than `low_water` unused numbers.
  /// of today (GET /pos/bootstrap `queue` shows the same); with enough left nothing new is reserved. Blocks are.
  /// cut one after another for the branch's business day, never overlapping: numbers the till prints from them.
  /// (online or offline) are never another till's. Needs a staff PIN session on a paired till (422.
  /// `terminal_required`) and orders.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/queue/blocks')
  Future<PostV1PosQueueBlocksResponse> posQueueReserve({
    @Header('Idempotency-Key') required String idempotencyKey,
  });
}
