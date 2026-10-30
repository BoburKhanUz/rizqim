// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_floor_response.dart';
import '../models/get_v1_pos_orders_response.dart';
import '../models/get_v1_pos_orders_uuid_response.dart';
import '../models/get_v1_pos_pickup_slots_response.dart';
import '../models/post_v1_pos_orders_response.dart';
import '../models/post_v1_pos_orders_uuid_accept_response.dart';
import '../models/post_v1_pos_orders_uuid_cancel_response.dart';
import '../models/post_v1_pos_orders_uuid_charges_charge_restore_response.dart';
import '../models/post_v1_pos_orders_uuid_charges_charge_waive_response.dart';
import '../models/post_v1_pos_orders_uuid_courses_course_send_response.dart';
import '../models/post_v1_pos_orders_uuid_discount_response.dart';
import '../models/post_v1_pos_orders_uuid_guests_response.dart';
import '../models/post_v1_pos_orders_uuid_items_item_seat_response.dart';
import '../models/post_v1_pos_orders_uuid_items_item_served_response.dart';
import '../models/post_v1_pos_orders_uuid_items_item_void_response.dart';
import '../models/post_v1_pos_orders_uuid_items_item_weight_response.dart';
import '../models/post_v1_pos_orders_uuid_merge_response.dart';
import '../models/post_v1_pos_orders_uuid_move_response.dart';
import '../models/post_v1_pos_orders_uuid_packaging_rule_remove_response.dart';
import '../models/post_v1_pos_orders_uuid_packaging_rule_restore_response.dart';
import '../models/post_v1_pos_orders_uuid_reject_response.dart';
import '../models/post_v1_pos_orders_uuid_request_bill_response.dart';
import '../models/post_v1_pos_orders_uuid_rounds_response.dart';
import '../models/post_v1_pos_orders_uuid_send_response.dart';
import '../models/post_v1_pos_orders_uuid_table_label_response.dart';
import '../models/post_v1_pos_orders_uuid_transfer_response.dart';
import '../models/put_v1_pos_orders_uuid_pickup_time_response.dart';
import '../models/scope2.dart';
import '../models/v1_pos_orders_request_body.dart';
import '../models/v1_pos_orders_uuid_accept_request_body.dart';
import '../models/v1_pos_orders_uuid_cancel_request_body.dart';
import '../models/v1_pos_orders_uuid_charges_charge_restore_request_body.dart';
import '../models/v1_pos_orders_uuid_charges_charge_waive_request_body.dart';
import '../models/v1_pos_orders_uuid_courses_course_send_request_body.dart';
import '../models/v1_pos_orders_uuid_discount_request_body.dart';
import '../models/v1_pos_orders_uuid_guests_request_body.dart';
import '../models/v1_pos_orders_uuid_items_item_seat_request_body.dart';
import '../models/v1_pos_orders_uuid_items_item_served_request_body.dart';
import '../models/v1_pos_orders_uuid_items_item_void_request_body.dart';
import '../models/v1_pos_orders_uuid_items_item_weight_request_body.dart';
import '../models/v1_pos_orders_uuid_merge_request_body.dart';
import '../models/v1_pos_orders_uuid_move_request_body.dart';
import '../models/v1_pos_orders_uuid_packaging_rule_remove_request_body.dart';
import '../models/v1_pos_orders_uuid_packaging_rule_restore_request_body.dart';
import '../models/v1_pos_orders_uuid_pickup_time_request_body.dart';
import '../models/v1_pos_orders_uuid_reject_request_body.dart';
import '../models/v1_pos_orders_uuid_request_bill_request_body.dart';
import '../models/v1_pos_orders_uuid_rounds_request_body.dart';
import '../models/v1_pos_orders_uuid_send_request_body.dart';
import '../models/v1_pos_orders_uuid_table_label_request_body.dart';
import '../models/v1_pos_orders_uuid_transfer_request_body.dart';

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
  /// `service_state` (P6-26): the hostess' mark, `cleaning` while a free place waits to be cleaned (a separate key,.
  /// `status` is unchanged). `reservation` names the guest (`contact_name`, `contact_phone`) for staff with orders.manage or bookings.manage.
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
  /// P6-13: `queue_number` (optional, with the `queue_numbers` module) is the number the till took from its block.
  /// (GET /pos/bootstrap `queue`): kept when it lies in this till's blocks of today and no order has it, otherwise.
  /// the order gets the next number; the answer's `queue.number` is the one to print.
  ///
  /// P6-21 / P6-25: a line's `course_no` (module `courses`: a later course is held until POST.
  /// /pos/orders/{uuid}/courses/{n}/send) and `awaiting_weight` (module `weight_later`: the quantity is the estimate,.
  /// the real kg comes with POST /pos/orders/{uuid}/items/{item}/weight; default: the product's `weight_mode`);.
  /// `table_label` the table plate of a counter order carried to the table (module `table_service`). A module that is.
  /// off: 403 `module_disabled`.
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
  /// `taken`, to refund or retain). Audited; the owner is alerted. P3-37: a closed order whose every payment was.
  /// refunded in full (`payment_status` refunded) is cancelled too, with the same permission: it becomes `canceled`,.
  /// keeps `closed_at`, its lines and paid bills stay, no receipt is issued (the refund receipts already reverse the.
  /// sale). Any other closed or cancelled order: 422 `order_closed`. 409 `version_conflict`.
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

  /// Send a course to the kitchen.
  ///
  /// P6-21: "N-kursni yuborish" — the held lines of course `course` (1–9) go to their stations (`sent_at`); lines.
  /// added to that course later go with the next ordinary send. 422 `nothing_to_send` when the course has nothing.
  /// left to send. Needs orders.manage; the waiter rule. Not gated by the `courses` module: an order that has courses.
  /// always sends them.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/courses/{course}/send')
  Future<PostV1PosOrdersUuidCoursesCourseSendResponse> posCourseSend({
    @Path('uuid') required String uuid,
    @Path('course') required String course,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidCoursesCourseSendRequestBody body,
  });

  /// Give or take off a discount.
  ///
  /// P3-37. `kind` `percent` (`value` 0 < v ≤ 100, two decimals) or `amount` (`value` in so'm, 0 < v ≤ the dishes'.
  /// subtotal); `value` 0 takes the discount off. `reason` is required unless `value` is 0 (up to 255). The.
  /// discount is on the dishes (`charges.items_subtotal`), before the service charge; `discount` is its amount in.
  /// whole so'm (half-up). A percent stays a percent: adding or voiding lines works it out again; an amount never.
  /// exceeds the dishes. Who: orders.discount — any discount; a cashier (pos.take_payment) up to the branch's.
  /// `pos_settings.cashier_discount_max_percent` (an amount by its percent of the dishes), while no bill was printed;.
  /// otherwise 403 `approval_required` (`permission: orders.discount`): send a manager's one-time `approval_token`.
  /// for orders.discount (POST /pos/approvals). Taking a discount off needs orders.manage only. The waiter rule.
  /// applies. 409 `paid_bill_affected` once a bill of the order has a payment or is paid (refund first); 409.
  /// `version_conflict`; 422 `order_closed`; 422 validation (`value` above the dishes, `reason` missing). A printed.
  /// bill no longer matches: bill again (POST /pos/orders/{uuid}/bills). Audited (`pos.order.discount`, old and.
  /// new); the owner is alerted at `pos_settings.alerts.discount_percent` and above; `order.items_changed` with.
  /// `change: charges_changed`. Not available offline (no sync operation).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/discount')
  Future<PostV1PosOrdersUuidDiscountResponse> posOrderDiscount({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidDiscountRequestBody body,
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

  /// Set a line's guest number.
  ///
  /// `seat_no` 1-500 puts the line on that guest for the split by guest (POST /pos/orders/{uuid}/bills, mode.
  /// `by_guest`); null takes it off every guest (it is shared). 422 `already_void`; 404 `item_not_found`. Needs.
  /// orders.manage; the waiter rule applies (`approval_token`). The guest_split module (403 `module_disabled`).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/items/{item}/seat')
  Future<PostV1PosOrdersUuidItemsItemSeatResponse> posOrderHandoverSeat({
    @Path('uuid') required String uuid,
    @Path('item') required String item,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidItemsItemSeatRequestBody body,
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

  /// Enter the weight of a line.
  ///
  /// P6-25: the real weight (`quantity`, kg or l, up to 3 decimals) of a line added with `awaiting_weight`: the line's.
  /// price follows it at its own unit price, `weighed_by` / `weighed_at` are set, the charges follow, and the order can.
  /// be billed again (no bill while a line awaits its weight: 409 `weight_pending`). A line weighed already: weighing.
  /// it again needs orders.void_item or a manager's `approval_token` (403 `approval_required`). A line that does not.
  /// wait for a weight: 422 `not_weighed_line`. Needs orders.manage; the waiter rule. Not gated by the module.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/items/{item}/weight')
  Future<PostV1PosOrdersUuidItemsItemWeightResponse> posCourseWeight({
    @Path('uuid') required String uuid,
    @Path('item') required String item,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidItemsItemWeightRequestBody body,
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

  /// Take a packaging line off the order.
  ///
  /// `reason` is required ("mijoz o'z paketini olib keldi"). The line stays on the order with `removed: true` and 0.
  /// so'm (charges.packaging_lines), the total goes down. 422 `packaging_not_found` (the order has no such line) or.
  /// `already_removed`; 409 `paid_bill_affected` once a bill of the order is being paid. Audited.
  /// (`pos.packaging_removed`).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/packaging/{rule}/remove')
  Future<PostV1PosOrdersUuidPackagingRuleRemoveResponse>
  posTakeawayRemovePackaging({
    @Path('uuid') required String uuid,
    @Path('rule') required String rule,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidPackagingRuleRemoveRequestBody body,
  });

  /// Put a packaging line back.
  ///
  /// 422 `not_removed`. Audited (`pos.packaging_restored`).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/packaging/{rule}/restore')
  Future<PostV1PosOrdersUuidPackagingRuleRestoreResponse>
  posTakeawayRestorePackaging({
    @Path('uuid') required String uuid,
    @Path('rule') required String rule,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidPackagingRuleRestoreRequestBody body,
  });

  /// Set the pickup time of a takeaway.
  ///
  /// `pickup_at` (ISO time): at least the branch's lead time from now (422 `pickup_too_soon`, `data.earliest_at`),.
  /// inside the window (422 `pickup_too_far`) and the opening hours (422 `pickup_closed`; the 24/7 module: any hour).
  /// The lines not sent yet wait and go to the kitchen at `pickup.kitchen_fire_at` (pickup_at − the prep time) by.
  /// themselves. `pickup_at: null` takes the time off: the kitchen gets the order now. 409 `already_in_kitchen` once.
  /// it was sent. Audited.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @PUT('/v1/pos/orders/{uuid}/pickup-time')
  Future<PutV1PosOrdersUuidPickupTimeResponse> posTakeawaySetPickupTime({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidPickupTimeRequestBody body,
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
  /// `note` per line. `send: true` sends the round's `now` lines at once. Needs orders.manage. P6-21 / P6-25:.
  /// `course_no` and `awaiting_weight` per line as on opening (a course after the one in the kitchen is held).
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

  /// Set the table plate.
  ///
  /// P6-25 (kafe "stolga olib kelish", module `table_service`): the number on the plate the guest took (`table_label`,.
  /// up to 20 characters; null or empty clears it), shown on the kitchen ticket and the order. Audited. Needs.
  /// orders.manage; the waiter rule.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/table-label')
  Future<PostV1PosOrdersUuidTableLabelResponse> posCourseTableLabel({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidTableLabelRequestBody body,
  });

  /// Hand the table to another waiter.
  ///
  /// The open order goes to `waiter_id` (an active waiter of the order's branch; 404 `waiter_not_found`, 422.
  /// `same_waiter`). Needs orders.transfer (a manager or the owner by default; it is enough on its own, without.
  /// orders.manage), or orders.manage with a manager's one-time `approval_token` for orders.transfer (POST.
  /// /pos/approvals; 403 `approval_required`). The waiter rule is not.
  /// asked: the manager decides. `reason` (optional, up to 255 characters) is kept in the audit log. The old and the.
  /// new waiter are told (broadcast `order.transferred` on their own channels), the floor refetches. Nothing already.
  /// paid changes; what the order earns from now on (a personal tip, sales for the waiter's pay) follows the new.
  /// waiter. The waiter_transfer module (403 `module_disabled`).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/orders/{uuid}/transfer')
  Future<PostV1PosOrdersUuidTransferResponse> posOrderHandoverTransfer({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosOrdersUuidTransferRequestBody body,
  });

  /// Pickup times the till may offer.
  ///
  /// Every `slot_minutes` from the earliest allowed time to the end of the window, inside the opening hours (24/7: all.
  /// day). `at` UTC, `time` the store's "HH:MM", `business_date` the day it belongs to.
  @GET('/v1/pos/pickup-slots')
  Future<GetV1PosPickupSlotsResponse> posTakeawaySlots();
}
