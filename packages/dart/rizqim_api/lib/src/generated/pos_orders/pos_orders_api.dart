// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_floor_response.dart';
import '../models/get_v1_pos_orders_uuid_response.dart';
import '../models/post_v1_pos_orders_response.dart';
import '../models/post_v1_pos_orders_uuid_charges_charge_restore_response.dart';
import '../models/post_v1_pos_orders_uuid_charges_charge_waive_response.dart';
import '../models/post_v1_pos_orders_uuid_guests_response.dart';
import '../models/post_v1_pos_orders_uuid_items_item_void_response.dart';
import '../models/post_v1_pos_orders_uuid_merge_response.dart';
import '../models/post_v1_pos_orders_uuid_move_response.dart';
import '../models/post_v1_pos_orders_uuid_rounds_response.dart';
import '../models/post_v1_pos_orders_uuid_send_response.dart';
import '../models/v1_pos_orders_request_body.dart';
import '../models/v1_pos_orders_uuid_charges_charge_restore_request_body.dart';
import '../models/v1_pos_orders_uuid_charges_charge_waive_request_body.dart';
import '../models/v1_pos_orders_uuid_guests_request_body.dart';
import '../models/v1_pos_orders_uuid_items_item_void_request_body.dart';
import '../models/v1_pos_orders_uuid_merge_request_body.dart';
import '../models/v1_pos_orders_uuid_move_request_body.dart';
import '../models/v1_pos_orders_uuid_rounds_request_body.dart';
import '../models/v1_pos_orders_uuid_send_request_body.dart';

part 'pos_orders_api.g.dart';

@RestApi()
abstract class PosOrdersApi {
  factory PosOrdersApi(Dio dio, {String? baseUrl}) = _PosOrdersApi;

  /// Floor map of a branch.
  ///
  /// Halls in order with their tables, rooms and tapchans, each with a `status`: `free`, `reserved_soon` (a booking.
  /// starts within the hour), `occupied`, `overdue` (open longer than the branch's alert, 6 hours by default) or.
  /// `bill_printed`, and the open orders on it (opened_at, guests, waiter, total, unsent lines). Closed seasonal halls.
  /// and inactive places are left out unless an order is still open there. `untabled_orders`: open POS orders without.
  /// a place. Refetch on `order.*` events of `pos-branch.{id}`. Needs orders.view; `branch_id` defaults to the.
  /// caller's (a terminal's) branch.
  ///
  /// [branchId] - The branch; defaults to the caller's or the terminal's branch.
  @GET('/v1/pos/floor')
  Future<GetV1PosFloorResponse> posFloorShow({
    @Query('branch_id') int? branchId,
  });

  /// Open a POS order.
  ///
  /// A hall order (`pos_hall`) at a table, room or tapchan (`table_id`) or without one, or a takeaway.
  /// (`pos_takeaway`). `guests_count` (hall: at least 1) drives the per-guest charges and the automatic "choy-non".
  /// lines. `items` (optional) is the first round; `send: true` sends its `now` lines to the kitchen at once.
  /// `external_id`: the device's own id for the order; a repeat returns the same order. A room with an open order is.
  /// 422 `table_occupied`; a closed zone or inactive place 422 `table_inactive`. Needs orders.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 without).
  @POST('/v1/pos/orders')
  Future<PostV1PosOrdersResponse> posOrderStore({
    @Body() required V1PosOrdersRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// One POS order.
  ///
  /// The order as the till shows it: place, guests, waiter, `version` (send it back with the next change), the.
  /// charge lines (`charges`: service charge %, room fee by day type, minimum spend and its top-up, waived lines) and.
  /// every line with its round, firing and kitchen state. Needs orders.view in the order's branch.
  @GET('/v1/pos/orders/{uuid}')
  Future<GetV1PosOrdersUuidResponse> posOrderShow({
    @Path('uuid') required String uuid,
  });

  /// Restore a waived charge line.
  ///
  /// Puts the charge back (orders.manage). 422 `not_waived` when it was not waived.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 without).
  @POST('/v1/pos/orders/{uuid}/charges/{charge}/restore')
  Future<PostV1PosOrdersUuidChargesChargeRestoreResponse>
  posOrderRestoreCharge({
    @Path('uuid') required String uuid,
    @Path('charge') required String charge,
    @Body() required V1PosOrdersUuidChargesChargeRestoreRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Waive a charge line.
  ///
  /// `service_charge`, `place_fee`, `room_fee` or `min_spend_topup` taken off this order with a `reason` (the guest.
  /// objected; the manager decided): orders.discount or a manager's `approval_token`. Audited. Needs orders.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 without).
  @POST('/v1/pos/orders/{uuid}/charges/{charge}/waive')
  Future<PostV1PosOrdersUuidChargesChargeWaiveResponse> posOrderWaive({
    @Path('uuid') required String uuid,
    @Path('charge') required String charge,
    @Body() required V1PosOrdersUuidChargesChargeWaiveRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Change the guests count.
  ///
  /// The per-guest charges follow; guests who joined get the automatic "choy-non" lines as a new round. Needs.
  /// orders.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 without).
  @POST('/v1/pos/orders/{uuid}/guests')
  Future<PostV1PosOrdersUuidGuestsResponse> posOrderGuests({
    @Path('uuid') required String uuid,
    @Body() required V1PosOrdersUuidGuestsRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Void a line.
  ///
  /// Before the line reached the kitchen: orders.manage, its stock and kazan portion go back. Once sent (or after the.
  /// bill was printed): `reason` is required and orders.void_item, or a manager's one-time `approval_token` (POST.
  /// /pos/approvals); the kitchen sees it struck through. `restock` (sent lines) returns the portion when it was not.
  /// cooked. 403 `approval_required` / `approval_invalid`; 422 `already_void`.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 without).
  @POST('/v1/pos/orders/{uuid}/items/{item}/void')
  Future<PostV1PosOrdersUuidItemsItemVoidResponse> posOrderVoid({
    @Path('uuid') required String uuid,
    @Path('item') required String item,
    @Body() required V1PosOrdersUuidItemsItemVoidRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Merge another order into this one.
  ///
  /// `source_uuid`'s lines move to this order (after its rounds), its guests are added and it is closed as merged.
  /// Both open hall orders of one branch, no printed bill, no payment; both versions are checked. Needs tables.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 without).
  @POST('/v1/pos/orders/{uuid}/merge')
  Future<PostV1PosOrdersUuidMergeResponse> posOrderMerge({
    @Path('uuid') required String uuid,
    @Body() required V1PosOrdersUuidMergeRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Move the order to another table.
  ///
  /// Another table, room or tapchan of the same branch: the new hall's service charge and the new place's room tariff.
  /// and minimum spend apply. A room with an open order is 422 `table_occupied` (merge instead). Needs tables.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 without).
  @POST('/v1/pos/orders/{uuid}/move')
  Future<PostV1PosOrdersUuidMoveResponse> posOrderMove({
    @Path('uuid') required String uuid,
    @Body() required V1PosOrdersUuidMoveRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Add a round of lines.
  ///
  /// A new round (`round_no`, who added it, when): lines priced by the order's channel, with the menu's rules (kg and.
  /// fractions in steps, modifiers, stop-list, kazan portions, stock; in fiscal mode the MXIK code). `fire_mode`.
  /// `now` (default) goes with the next send, `hold` only when sent by id; `due_at` for a pre-order; `seat_no` and.
  /// `note` per line. `send: true` sends the round's `now` lines at once. Needs orders.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 without).
  @POST('/v1/pos/orders/{uuid}/rounds')
  Future<PostV1PosOrdersUuidRoundsResponse> posOrderAddRound({
    @Path('uuid') required String uuid,
    @Body() required V1PosOrdersUuidRoundsRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Send lines to the kitchen.
  ///
  /// Without `item_ids`: every line not sent yet whose `fire_mode` is `now`. With them: exactly those (held lines.
  /// included). Each gets its kitchen station and `sent_at`; the first send starts the order (preparing). 422.
  /// `nothing_to_send` when there is nothing new. Needs orders.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 without).
  @POST('/v1/pos/orders/{uuid}/send')
  Future<PostV1PosOrdersUuidSendResponse> posOrderSend({
    @Path('uuid') required String uuid,
    @Body() required V1PosOrdersUuidSendRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });
}
