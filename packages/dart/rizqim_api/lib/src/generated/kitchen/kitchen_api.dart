// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_staff_kitchen_tickets_response.dart';
import '../models/post_v1_staff_orders_uuid_items_item_ready_response.dart';
import '../models/post_v1_staff_orders_uuid_items_item_recall_response.dart';

part 'kitchen_api.g.dart';

@RestApi()
abstract class KitchenApi {
  factory KitchenApi(Dio dio, {String? baseUrl}) = _KitchenApi;

  /// Kitchen display tickets.
  ///
  /// The orders the kitchen is cooking (accepted and preparing; with `include_ready=1` also the ready ones not yet.
  /// handed over), oldest first, at most 200 (`meta.truncated`). P2-33: also an order a courier was assigned to while.
  /// it was cooking (courier_assigned) as long as a line is still pending; with `include_ready=1` every.
  /// courier_assigned order (the courier has not picked it up yet). Each ticket: lines with modifiers and.
  /// quantities/weights, each line's kitchen state, the notes (phone numbers masked), when it came in and was.
  /// accepted, and `waiting_seconds`. No customer data, no prices. Needs kitchen.manage or orders.view.
  ///
  /// P3-26: with `canceled_within` (minutes, 1–60) the orders cancelled or rejected within that time that had.
  /// reached the kitchen (a line sent, or an online order accepted) are listed too, after the live ones, with their.
  /// `status` (canceled / rejected) and `canceled_at`, their lines as they were (voided ones with `voided_at`), so a.
  /// display strikes them through instead of losing them; `canceled_at` is null on a live ticket.
  ///
  /// [branchId] - One branch; omit for every branch the caller works in (a branch terminal: its branch).
  ///
  /// [includeReady] - 1 (or true) to also list orders that are ready but not handed over yet.
  ///
  /// [canceledWithin] - Minutes (1-60): also list the orders cancelled or rejected that long ago at most, with canceled_at.
  @GET('/v1/staff/kitchen/tickets')
  Future<GetV1StaffKitchenTicketsResponse> staffOrderTickets({
    @Query('branch_id') int? branchId,
    @Query('include_ready') bool? includeReady,
    @Query('canceled_within') int? canceledWithin,
  });

  /// Bump a line ready (kitchen display).
  ///
  /// The line is ready (kitchen.manage). The first bump of an accepted order starts it (preparing). When every line is.
  /// ready a delivery or pickup order becomes ready by itself; a hall order (dine_in) stays preparing until it is.
  /// closed. P2-33: a courier_assigned order (a courier was assigned while it was cooking) takes bumps too, with no.
  /// status move. 422 when the order is not in the kitchen (new, ready, on its way, finished). Bumping a ready line.
  /// again answers 200 unchanged. P3-26: a held pre-order line with a due time (fire_mode hold, `due_at`, not sent yet).
  /// may be bumped before the till sends it — ready implies sent: it gets its station and `sent_at` now and the POS.
  /// order's version moves. Returns the ticket.
  @POST('/v1/staff/orders/{uuid}/items/{item}/ready')
  Future<PostV1StaffOrdersUuidItemsItemReadyResponse> staffOrderBumpItem({
    @Path('uuid') required String uuid,
    @Path('item') required String item,
  });

  /// Recall a line (kitchen display).
  ///
  /// Undo a mistaken bump: the line is back to pending (kitchen.manage). While the order is accepted or preparing,.
  /// and (P2-30) while it is ready but not yet with a courier or handed over: the order goes back to preparing.
  /// (`order.status_changed` and the history say so). P2-33: while a courier is assigned (courier_assigned) the line.
  /// is recalled with no status move. 422 once it is on its way or finished. Returns the ticket.
  @POST('/v1/staff/orders/{uuid}/items/{item}/recall')
  Future<PostV1StaffOrdersUuidItemsItemRecallResponse> staffOrderRecallItem({
    @Path('uuid') required String uuid,
    @Path('item') required String item,
  });
}
