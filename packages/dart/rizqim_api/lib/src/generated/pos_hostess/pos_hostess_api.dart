// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_waitlist_response.dart';
import '../models/patch_v1_pos_waitlist_uuid_response.dart';
import '../models/post_v1_pos_waitlist_uuid_cancel_response.dart';
import '../models/post_v1_pos_waitlist_uuid_no_show_response.dart';
import '../models/post_v1_pos_waitlist_uuid_notify_response.dart';
import '../models/post_v1_pos_waitlist_uuid_seat_response.dart';
import '../models/put_v1_pos_tables_id_state_response.dart';
import '../models/v1_pos_tables_id_state_request_body.dart';
import '../models/v1_pos_waitlist_request_body.dart';
import '../models/v1_pos_waitlist_uuid_cancel_request_body.dart';
import '../models/v1_pos_waitlist_uuid_no_show_request_body.dart';
import '../models/v1_pos_waitlist_uuid_notify_request_body.dart';
import '../models/v1_pos_waitlist_uuid_request_body.dart';
import '../models/v1_pos_waitlist_uuid_seat_request_body.dart';

part 'pos_hostess_api.g.dart';

@RestApi()
abstract class PosHostessApi {
  factory PosHostessApi(Dio dio, {String? baseUrl}) = _PosHostessApi;

  /// Mark a table's state.
  ///
  /// `state`: `cleaning` (the guests left, the table is not ready) or `free` (ready: clears the mark). The mark shows.
  /// as `service_state` on the floor map while the place has no open order; opening an order there clears it by.
  /// itself, and it never blocks seating. Needs hostess.manage or tables.manage in the table's branch; the hostess.
  /// module (403 `module_disabled`). Announced as `table.state_changed` on the branch's channel.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @PUT('/v1/pos/tables/{id}/state')
  Future<PutV1PosTablesIdStateResponse> posTableStateUpdate({
    @Path('id') required String id,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosTablesIdStateRequestBody body,
  });

  /// The waitlist.
  ///
  /// The branch's entries of a business day (default today's), in the order the guests came, each with its place in.
  /// the queue (`position`, waiting entries only), how long it has waited and the wait the hostess quoted; `summary`.
  /// counts them by state. Filter by `status`. Needs hostess.manage; the hostess module (403 `module_disabled`).
  ///
  /// [branchId] - The branch; defaults to the caller's or the terminal's branch.
  ///
  /// [date] - Business day, Y-m-d (default: today).
  ///
  /// [status] - One state: waiting, seated, canceled or no_show.
  @GET('/v1/pos/waitlist')
  Future<GetV1PosWaitlistResponse> posWaitlistIndex({
    @Query('branch_id') int? branchId,
    @Query('date') String? date,
    @Query('status') String? status,
  });

  /// Write a guest on the waitlist.
  ///
  /// `guest_name`, `party_size` (1-200), optional `guest_phone` (stored as +998XXXXXXXXX), `quoted_wait_minutes`.
  /// (0-600: what the hostess told the guest) and `note`. Announced as `waitlist.changed` on the branch's channel.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/waitlist')
  Future<dynamic> posWaitlistStore({
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosWaitlistRequestBody body,
  });

  /// Change an entry.
  ///
  /// Only the fields sent change (name, phone, party size, quoted wait, note) and only while the entry is waiting.
  /// (409 `entry_closed`).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @PATCH('/v1/pos/waitlist/{uuid}')
  Future<PatchV1PosWaitlistUuidResponse> posWaitlistUpdate({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() V1PosWaitlistUuidRequestBody? body,
  });

  /// The guest left.
  ///
  /// Closes a waiting entry as `canceled`; optional `reason`.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/waitlist/{uuid}/cancel')
  Future<PostV1PosWaitlistUuidCancelResponse> posWaitlistCancel({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() V1PosWaitlistUuidCancelRequestBody? body,
  });

  /// The guest never came.
  ///
  /// Closes a waiting entry as `no_show` (usually after calling them).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/waitlist/{uuid}/no-show')
  Future<PostV1PosWaitlistUuidNoShowResponse> posWaitlistNoShow({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() V1PosWaitlistUuidNoShowRequestBody? body,
  });

  /// Call the guest ("stol tayyor").
  ///
  /// Stamps `notified_at` on a waiting entry (calling again moves it). Nothing is sent to the guest: the hostess calls.
  /// them with the number on the entry.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/waitlist/{uuid}/notify')
  Future<PostV1PosWaitlistUuidNotifyResponse> posWaitlistNotify({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() V1PosWaitlistUuidNotifyRequestBody? body,
  });

  /// Seat the party.
  ///
  /// Gives the waiting party `dining_table_id` (an active place of the branch; 422 `table_not_found`). With.
  /// `open_order: true` the party's hall order is opened there (`party_size` guests; optional `waiter_id`, else the.
  /// opener if a waiter) and linked to the entry (`order_uuid`); the order needs orders.manage. 409 `entry_closed`.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/waitlist/{uuid}/seat')
  Future<PostV1PosWaitlistUuidSeatResponse> posWaitlistSeat({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosWaitlistUuidSeatRequestBody body,
  });
}
