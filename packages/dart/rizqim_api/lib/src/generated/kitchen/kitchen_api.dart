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
  /// handed over), oldest first, at most 200 (`meta.truncated`). Each ticket: lines with modifiers and.
  /// quantities/weights, each line's kitchen state, the notes (phone numbers masked), when it came in and was.
  /// accepted, and `waiting_seconds`. No customer data, no prices. Needs kitchen.manage or orders.view.
  ///
  /// [branchId] - One branch; omit for every branch the caller works in (a branch terminal: its branch).
  ///
  /// [includeReady] - 1 to also list orders that are ready but not handed over yet.
  @GET('/v1/staff/kitchen/tickets')
  Future<GetV1StaffKitchenTicketsResponse> staffOrderTickets({
    @Query('branch_id') int? branchId,
    @Query('include_ready') bool? includeReady,
  });

  /// Bump a line ready (kitchen display).
  ///
  /// The line is ready (kitchen.manage). The first bump of an accepted order starts it (preparing). When every line is.
  /// ready a delivery or pickup order becomes ready by itself; a hall order (dine_in) stays preparing until it is.
  /// closed. 422 when the order is not in the kitchen (new, ready, on its way, finished). Bumping a ready line again.
  /// answers 200 unchanged. Returns the ticket.
  @POST('/v1/staff/orders/{uuid}/items/{item}/ready')
  Future<PostV1StaffOrdersUuidItemsItemReadyResponse> staffOrderBumpItem({
    @Path('uuid') required String uuid,
    @Path('item') required String item,
  });

  /// Recall a line (kitchen display).
  ///
  /// Undo a mistaken bump: the line is back to pending (kitchen.manage). Only while the order is accepted or.
  /// preparing (422 once it is ready). Returns the ticket.
  @POST('/v1/staff/orders/{uuid}/items/{item}/recall')
  Future<PostV1StaffOrdersUuidItemsItemRecallResponse> staffOrderRecallItem({
    @Path('uuid') required String uuid,
    @Path('item') required String item,
  });
}
