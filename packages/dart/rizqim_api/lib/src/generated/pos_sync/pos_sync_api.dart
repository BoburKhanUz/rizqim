// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_sync_errors_response.dart';
import '../models/get_v1_pos_sync_status_response.dart';
import '../models/post_v1_pos_sync_errors_op_id_resolve_response.dart';
import '../models/post_v1_pos_sync_response.dart';
import '../models/v1_pos_sync_errors_op_id_resolve_request_body.dart';
import '../models/v1_pos_sync_request_body.dart';

part 'pos_sync_api.g.dart';

@RestApi()
abstract class PosSyncApi {
  factory PosSyncApi(Dio dio, {String? baseUrl}) = _PosSyncApi;

  /// Send the offline queue.
  ///
  /// Up to 50 operations of this till in its `seq` order, each with a new `op_id` (UUID; the same one when sent again:.
  /// it is applied once), `type` (order.open, order.round, order.send, order.void, order.served, bill.create, bill.pay,.
  /// order.close), `occurred_at` (the device's clock), `staff_id` (who did it: signed in on this till before the.
  /// outage, with the permission) and `payload`. Each result: `status` applied | conflict (applied, but a manager.
  /// should look: oversold, payment on a cancelled order, amount mismatch, stale menu, a taken room) | rejected (not.
  /// applied: "Sinxronlash xatolari"), `reason`, `message` and `refs` (the server's order uuid and version, local.
  /// number, line ids, bill and payment uuids, flags). `sent_at` (the device's clock now) gives the clock skew: more.
  /// than 5 minutes is `clock_warning`. `queue` (size, oldest_at) is what is still in the device's queue including.
  /// this batch: the shift does not close while it is not empty. Only a POS terminal's own token.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/sync')
  Future<PostV1PosSyncResponse> posSyncStore({
    @Body() required V1PosSyncRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Sync errors ("Sinxronlash xatolari").
  ///
  /// Offline operations of the caller's branch (or `branch_id`) that a manager should look at: rejected (not applied).
  /// and conflicting (applied with a difference), not resolved yet; `all=1` resolved ones too; `device` (uuid) one.
  /// till. Newest first, 50 per page. Needs orders.view.
  ///
  /// [device] - A till uuid.
  ///
  /// [all] - Resolved and dismissed ones too.
  @GET('/v1/pos/sync/errors')
  Future<GetV1PosSyncErrorsResponse> posSyncErrors({
    @Query('branch_id') int? branchId,
    @Query('device') String? device,
    @Query('all') bool? all,
    @Query('page') int? page,
  });

  /// Resolve or dismiss a sync error.
  ///
  /// The manager settled it by hand (`resolution: resolved`, e.g. entered the round again, refunded the guest) or.
  /// there is nothing to do (`dismissed`), with an optional `note`. A resolved rejected operation no longer keeps its.
  /// till's shift from closing. Repeating it changes nothing. Needs orders.cancel (owner, manager).
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/sync/errors/{opId}/resolve')
  Future<PostV1PosSyncErrorsOpIdResolveResponse> posSyncResolve({
    @Path('opId') required String opId,
    @Body() required V1PosSyncErrorsOpIdResolveRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Sync status of this till.
  ///
  /// What the server holds for this till: the last `seq` it received, what the till last said about its queue, the.
  /// clock skew, and its operations that need a manager (rejected or conflicting, not resolved yet; newest first, at.
  /// most 100). `queue_size` / `oldest_at` (optional) report the queue now (0 after it drained). Only a POS terminal's.
  /// own token.
  ///
  /// [queueSize] - Operations still in the device queue now.
  ///
  /// [oldestAt] - When the oldest of them happened (device clock).
  @GET('/v1/pos/sync/status')
  Future<GetV1PosSyncStatusResponse> posSyncStatus({
    @Query('queue_size') int? queueSize,
    @Query('oldest_at') String? oldestAt,
  });
}
