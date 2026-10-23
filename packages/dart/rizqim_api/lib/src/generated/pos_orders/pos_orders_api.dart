// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_floor_response.dart';
import '../models/get_v1_pos_orders_response.dart';
import '../models/get_v1_pos_orders_uuid_response.dart';
import '../models/post_v1_pos_orders_response.dart';
import '../models/post_v1_pos_orders_uuid_accept_response.dart';
import '../models/post_v1_pos_orders_uuid_cancel_response.dart';
import '../models/post_v1_pos_orders_uuid_charges_charge_restore_response.dart';
import '../models/post_v1_pos_orders_uuid_charges_charge_waive_response.dart';
import '../models/post_v1_pos_orders_uuid_guests_response.dart';
import '../models/post_v1_pos_orders_uuid_items_item_served_response.dart';
import '../models/post_v1_pos_orders_uuid_items_item_void_response.dart';
import '../models/post_v1_pos_orders_uuid_merge_response.dart';
import '../models/post_v1_pos_orders_uuid_move_response.dart';
import '../models/post_v1_pos_orders_uuid_reject_response.dart';
import '../models/post_v1_pos_orders_uuid_request_bill_response.dart';
import '../models/post_v1_pos_orders_uuid_rounds_response.dart';
import '../models/post_v1_pos_orders_uuid_send_response.dart';
import '../models/scope2.dart';
import '../models/v1_pos_orders_request_body.dart';
import '../models/v1_pos_orders_uuid_accept_request_body.dart';
import '../models/v1_pos_orders_uuid_cancel_request_body.dart';
import '../models/v1_pos_orders_uuid_charges_charge_restore_request_body.dart';
import '../models/v1_pos_orders_uuid_charges_charge_waive_request_body.dart';
import '../models/v1_pos_orders_uuid_guests_request_body.dart';
import '../models/v1_pos_orders_uuid_items_item_served_request_body.dart';
import '../models/v1_pos_orders_uuid_items_item_void_request_body.dart';
import '../models/v1_pos_orders_uuid_merge_request_body.dart';
import '../models/v1_pos_orders_uuid_move_request_body.dart';
import '../models/v1_pos_orders_uuid_reject_request_body.dart';
import '../models/v1_pos_orders_uuid_request_bill_request_body.dart';
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
  /// `bill_printed`, and the open orders on it (opened_at, guests, waiter, total, `unsent_count`: lines not sent to the.
  /// kitchen yet, `ready_count`: lines the kitchen marked ready and nobody served yet, `bill_requested`: a waiter asked.
  /// for the bill, "Schyot so'raldi"; the place's own `bill_requested` while no bill is printed yet). A place's.
  /// `reservation` names the guest (`contact_name`, `contact_phone`) for staff with orders.manage or bookings.manage.
  /// (null for others, e.g. a kitchen screen). `layout`: grid cells of the floor plan (40 columns x 24 rows, x/y the.
  /// top-left cell, w/h the size in cells; null: not placed yet). Closed seasonal halls.
  /// and inactive places are left out unless an order is still open there. `untabled_orders`: open POS orders without.
  /// a place. Refetch on `order.*` events of `pos-branch.{id}`. Needs orders.view; `branch_id` defaults to the.
  /// caller's (a terminal's) branch.
  ///
  /// [branchId] - The branch; defaults to the caller's or the terminal's branch.
  @GET('/v1/pos/floor')
  Future<GetV1PosFloorResponse> posFloorShow({
    @Query('branch_id') int? branchId,
  });

  /// Orders of the branch for the till.
  ///
  /// `scope`:.
  ///
  ///  - `open` (the default): one queue for the till (POS_SPEC 2.5): every POS order still open (accepted, preparing,.
  ///    ready, not closed — paid ones still waiting for their receipt included, `fiscal_pending`) and every accepted.
  ///    online order (Telegram, mobile, aggregator: accepted, preparing, ready), oldest first, at most 300;.
  ///  - `incoming` (P3-26): the online orders waiting to be accepted (`new`), oldest first: accept or reject them with.
  ///    POST /pos/orders/{uuid}/accept|reject;.
  ///  - `closed` (P3-26): the POS orders closed (paid) on one business day (`business_date`, YYYY-MM-DD; default: the.
  ///    branch's business day now), newest first, at most 300: the till's list to refund from.
  ///
  /// `bill_requested`: a waiter asked for the bill. `online`: an online order's board card (customer, address, lines,.
  /// payment; contact details for orders.manage), null for a POS order. `branch_id`: the caller's branch by default (a terminal PIN.
  /// session: its terminal's). Needs orders.view in the branch.
  ///
  /// [scope] - open (default) | incoming | closed.
  ///
  /// [businessDate] - scope=closed: the business day (YYYY-MM-DD); default: the branch's business day now.
  ///
  /// [branchId] - The branch; default: the caller's (or their terminal's).
  @GET('/v1/pos/orders')
  Future<GetV1PosOrdersResponse> posOrderIndex({
    @Query('scope') Scope2? scope,
    @Query('business_date') String? businessDate,
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
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders')
  Future<PostV1PosOrdersResponse> posOrderStore({
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersRequestBody body,
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

  /// Accept an online order.
  ///
  /// A new online order (Telegram, mobile, aggregator) of the branch is accepted at the till: `new` → `accepted`.
  /// (the customer is told, the kitchen gets it). Accepted already: 200 unchanged. Not new any more (rejected,.
  /// cancelled): 422. A POS order: 422 `not_online_order`. Optional `version` (409 when stale). Needs orders.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/accept')
  Future<PostV1PosOrdersUuidAcceptResponse> posOrderAccept({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() V1PosOrdersUuidAcceptRequestBody? body,
  });

  /// Cancel the whole order.
  ///
  /// `reason` is required. orders.cancel, or a manager's one-time `approval_token` for orders.cancel (POST.
  /// /pos/approvals; 403 `approval_required`). An order with a payment is refused: 409 `has_payments` (refund the.
  /// payments first, POST /pos/refunds). Every line still on the order is voided — lines already sent get a kitchen.
  /// void ("O'CHIRILDI"), unsent lines give their stock and kazan portion back — open or printed bills are voided, the.
  /// order becomes `canceled` and its table free; a booking's order completes the booking (its deposits stay.
  /// `taken`, to refund or retain). Audited; the owner is alerted. 422 `order_closed`, 409 `version_conflict`.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/cancel')
  Future<PostV1PosOrdersUuidCancelResponse> posOrderCancel({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidCancelRequestBody body,
  });

  /// Restore a waived charge line.
  ///
  /// Puts the charge back (orders.manage). 422 `not_waived` when it was not waived.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/charges/{charge}/restore')
  Future<PostV1PosOrdersUuidChargesChargeRestoreResponse>
  posOrderRestoreCharge({
    @Path('uuid') required String uuid,
    @Path('charge') required String charge,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidChargesChargeRestoreRequestBody body,
  });

  /// Waive a charge line.
  ///
  /// `service_charge`, `place_fee`, `room_fee` or `min_spend_topup` taken off this order with a `reason` (the guest.
  /// objected; the manager decided): orders.discount or a manager's `approval_token`. Audited. Needs orders.manage.
  /// 409 `paid_bill_affected` when a paid bill carries it (P3-35).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/charges/{charge}/waive')
  Future<PostV1PosOrdersUuidChargesChargeWaiveResponse> posOrderWaive({
    @Path('uuid') required String uuid,
    @Path('charge') required String charge,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidChargesChargeWaiveRequestBody body,
  });

  /// Change the guests count.
  ///
  /// The per-guest charges follow; guests who joined get the automatic "choy-non" lines as a new round. Needs.
  /// orders.manage. 409 `paid_bill_affected` when a charge a paid bill carries would go down (P3-35).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/guests')
  Future<PostV1PosOrdersUuidGuestsResponse> posOrderGuests({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidGuestsRequestBody body,
  });

  /// Mark a line served.
  ///
  /// The line reached the guests: `ready` (the kitchen display bumped it) or `sent` (a station without a display,.
  /// e.g. tea) becomes `served`. 422 `already_served`, `item_not_ready` (not sent yet or voided). Needs orders.manage;.
  /// the waiter rule applies (`approval_token`).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/items/{item}/served')
  Future<PostV1PosOrdersUuidItemsItemServedResponse> posOrderServed({
    @Path('uuid') required String uuid,
    @Path('item') required String item,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidItemsItemServedRequestBody body,
  });

  /// Void a line.
  ///
  /// Before the line reached the kitchen: orders.manage, its stock and kazan portion go back. Once sent (or after the.
  /// bill was printed): `reason` is required and orders.void_item, or a manager's one-time `approval_token` (POST.
  /// /pos/approvals); the kitchen sees it struck through. `restock` (sent lines) returns the portion when it was not.
  /// cooked. 403 `approval_required` / `approval_invalid`; 422 `already_void`; 409 `paid_bill_affected` for a line on a.
  /// bill being paid (refund that payment first, P3-35).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/items/{item}/void')
  Future<PostV1PosOrdersUuidItemsItemVoidResponse> posOrderVoid({
    @Path('uuid') required String uuid,
    @Path('item') required String item,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidItemsItemVoidRequestBody body,
  });

  /// Merge another order into this one.
  ///
  /// `source_uuid`'s lines move to this order (after its rounds), its guests are added and it is closed as merged.
  /// Both open hall orders of one branch, no printed bill, no payment; both versions are checked. Needs tables.manage.
  /// P3-35: a booking's order hands its booking and deposits to this order; orders of two different bookings are 422.
  /// `merge_bookings_conflict`.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/merge')
  Future<PostV1PosOrdersUuidMergeResponse> posOrderMerge({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidMergeRequestBody body,
  });

  /// Move the order to another table.
  ///
  /// Another table, room or tapchan of the same branch: the new hall's service charge and the new place's room tariff.
  /// and minimum spend apply. A room with an open order is 422 `table_occupied` (merge instead). Needs tables.manage.
  /// 409 `paid_bill_affected` when a charge a paid bill carries would go down (P3-35).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/move')
  Future<PostV1PosOrdersUuidMoveResponse> posOrderMove({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidMoveRequestBody body,
  });

  /// Reject an online order.
  ///
  /// A new online order is rejected at the till: `new` → `rejected` (the customer is told; an online payment is.
  /// returned as for any rejection). `reason` (required, what the customer and the owner read) and optional.
  /// `cancel_reason` (customer_request, out_of_stock, store_closed, courier_unavailable, other; default other). Needs.
  /// orders.cancel, or a manager's one-time `approval_token` for orders.cancel (POST /pos/approvals; 403.
  /// `approval_required`). Rejected already: 200 unchanged; not new any more: 422. Optional `version`.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/reject')
  Future<PostV1PosOrdersUuidRejectResponse> posOrderReject({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidRejectRequestBody body,
  });

  /// Ask the till for the bill.
  ///
  /// The waiter's "Schyot so'rash": the order is marked `bill_requested_at` and the till hears.
  ///      * `order.bill_requested` on `pos-branch.{id}` (the floor map shows "Schyot so'raldi"); no bill is made here, the.
  /// cashier makes and prints it. Asked already: 200 with the order unchanged. Optional `version` (409 when stale).
  /// Needs orders.manage; the waiter rule applies (`approval_token`).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/request-bill')
  Future<PostV1PosOrdersUuidRequestBillResponse> posOrderRequestBill({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() V1PosOrdersUuidRequestBillRequestBody? body,
  });

  /// Add a round of lines.
  ///
  /// A new round (`round_no`, who added it, when): lines priced by the order's channel, with the menu's rules (kg and.
  /// fractions in steps, modifiers, stop-list, kazan portions, stock; in fiscal mode the MXIK code). `fire_mode`.
  /// `now` (default) goes with the next send, `hold` only when sent by id; `due_at` for a pre-order; `seat_no` and.
  /// `note` per line. `send: true` sends the round's `now` lines at once. Needs orders.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/rounds')
  Future<PostV1PosOrdersUuidRoundsResponse> posOrderAddRound({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidRoundsRequestBody body,
  });

  /// Send lines to the kitchen.
  ///
  /// Without `item_ids`: every line not sent yet whose `fire_mode` is `now`. With them: exactly those (held lines.
  /// included). Each gets its kitchen station and `sent_at`; the first send starts the order (preparing). 422.
  /// `nothing_to_send` when there is nothing new. Needs orders.manage.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/send')
  Future<PostV1PosOrdersUuidSendResponse> posOrderSend({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidSendRequestBody body,
  });
}
