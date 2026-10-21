// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_staff_orders_response.dart';
import '../models/get_v1_staff_orders_uuid_response.dart';
import '../models/post_v1_staff_orders_uuid_status_response.dart';
import '../models/scope.dart';
import '../models/type4.dart';
import '../models/v1_staff_orders_uuid_status_request_body.dart';

part 'staff_orders_api.g.dart';

@RestApi()
abstract class StaffOrdersApi {
  factory StaffOrdersApi(Dio dio, {String? baseUrl}) = _StaffOrdersApi;

  /// Branch orders for staff (board).
  ///
  /// The orders of the caller's branches, newest first, keyset-paginated: send `meta.next_cursor` back as `cursor`.
  /// (with the same filters) until it is null. Filters: `branch_id` (one branch; another branch the caller does not.
  /// work in is 403, another store's 404), `scope` (`active`: still in progress, `history`: delivered, cancelled,.
  /// rejected, refunded), `status` (comma-separated), `type`, `channel`, `date` or `from`/`to` (days in the store's.
  /// time zone, at most 93 days), `q` (the start of the order number). Needs orders.view (owner, manager, cashier,.
  /// waiter, cook). Contact details and the address only for orders.manage. P2-30: `waiting_seconds` as on the kitchen.
  /// tickets (from acceptance, or creation, to now or to when it became ready).
  ///
  /// [branchId] - One branch of the store; omit for every branch the caller works in.
  ///
  /// [scope] - `active` (in progress) or `history` (finished).
  ///
  /// [status] - Comma-separated order statuses, e.g. `new,accepted`.
  ///
  /// [type] - delivery, pickup or dine_in.
  ///
  /// [channel] - telegram, mobile, pos_hall, pos_takeaway or aggregator.
  ///
  /// [date] - One day (Y-m-d, store time zone); same as from=to=date.
  ///
  /// [from] - First day (Y-m-d, store time zone), inclusive.
  ///
  /// [to] - Last day (Y-m-d, store time zone), inclusive.
  ///
  /// [q] - Start of the order number (short code), 1-20 letters or digits.
  ///
  /// [perPage] - Page size, 1-100 (larger values are capped).
  ///
  /// [cursor] - `meta.next_cursor` of the previous page.
  @GET('/v1/staff/orders')
  Future<GetV1StaffOrdersResponse> staffOrderIndex({
    @Query('per_page') int? perPage = 30,
    @Query('branch_id') int? branchId,
    @Query('scope') Scope? scope,
    @Query('status') String? status,
    @Query('type') Type4? type,
    @Query('channel') String? channel,
    @Query('date') String? date,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('q') String? q,
    @Query('cursor') String? cursor,
  });

  /// One order for staff (detail).
  ///
  /// Items with modifiers, weights and kitchen state, notes, every timestamp and the status history. 404 for an order.
  /// of another store or of a branch the caller does not work in.
  @GET('/v1/staff/orders/{uuid}')
  Future<GetV1StaffOrdersUuidResponse> staffOrderShow({
    @Path('uuid') required String uuid,
  });

  /// Change an order's status (staff).
  ///
  /// By permission (docs/AUTH.md §6): orders.manage (owner, manager, cashier, waiter) accepts, starts, marks ready and.
  /// hands over (pickup, hall: delivered); a cook (kitchen.manage) only starts cooking (accepted → preparing) and marks.
  /// ready (preparing → ready); cancelling or rejecting needs orders.cancel and a `reason`. The order's state machine.
  /// decides what can follow what (422 otherwise). Couriers are assigned with POST /orders/{uuid}/assign-courier.
  /// Marking an order ready marks all its lines ready in the kitchen. The same status again answers 200 unchanged.
  /// P2-30: `on_the_way` (the assigned courier picked the order up: courier_assigned → on_the_way) with.
  /// orders.assign_courier; an order without an assigned courier is a 422 (assign one first).
  @POST('/v1/staff/orders/{uuid}/status')
  Future<PostV1StaffOrdersUuidStatusResponse> staffOrderUpdateStatus({
    @Path('uuid') required String uuid,
    @Body() required V1StaffOrdersUuidStatusRequestBody body,
  });
}
