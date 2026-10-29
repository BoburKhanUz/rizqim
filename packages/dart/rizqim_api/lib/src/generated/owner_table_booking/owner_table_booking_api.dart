// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_branches_id_booking_rules_response.dart';
import '../models/get_v1_owner_waitlist_response.dart';
import '../models/put_v1_owner_branches_id_booking_rules_response.dart';
import '../models/v1_owner_branches_id_booking_rules_request_body.dart';

part 'owner_table_booking_api.g.dart';

@RestApi()
abstract class OwnerTableBookingApi {
  factory OwnerTableBookingApi(Dio dio, {String? baseUrl}) =
      _OwnerTableBookingApi;

  /// The branch's table booking rules.
  ///
  /// Every key with its default when nothing was saved (`configured: false`): `table_slot_minutes` (30-480, 120) and.
  /// `room_slot_minutes` / `tapchan_slot_minutes` (null: the branch's `booking_slot_minutes`, 5 hours); the least time.
  /// ahead a booking may be made per place kind (`*_min_advance_minutes` 0-1440, 0: no limit; the choyxona's "kamida.
  ///      * 3 soat" is 180 for rooms) and `max_advance_days` (null: no limit); the deposit: `deposit_mode` (a BookingDepositMode.
  /// value), `deposit_amount`, `deposit_from_guests` (a party of this many or more) and `deposit_required_to_confirm`.
  @GET('/v1/owner/branches/{id}/booking-rules')
  Future<GetV1OwnerBranchesIdBookingRulesResponse> branchBookingRulesShow({
    @Path('id') required int id,
  });

  /// Change the branch's table booking rules.
  ///
  /// Send only what changes. The deposit applies to table bookings only (rooms and tapchans keep taking deposits by.
  /// hand) and is taken as the existing booking deposit with its advance receipt. With `deposit_required_to_confirm` a.
  /// booking stays a request (409 `deposit_required` on confirm) until the deposits taken add up to the amount.
  /// Audited.
  @PUT('/v1/owner/branches/{id}/booking-rules')
  Future<PutV1OwnerBranchesIdBookingRulesResponse> branchBookingRulesUpdate({
    @Path('id') required int id,
    @Body() V1OwnerBranchesIdBookingRulesRequestBody? body,
  });

  /// The waitlist, by day.
  ///
  /// The branch's entries from `from` to `to` (business days, Y-m-d, `to` inclusive, at most 31 days, default.
  /// today), newest first, with the guest's number masked, and a `summary` (how many were seated, left or never.
  /// came, the wait quoted against the wait the seated parties had). Filter by `status`. At most 500 entries. Needs.
  /// reports.view; the hostess module (403 `module_disabled`).
  ///
  /// [branchId] - The branch; defaults to the caller's.
  ///
  /// [from] - First business day, Y-m-d (default today).
  ///
  /// [to] - Last business day, Y-m-d, inclusive.
  ///
  /// [status] - One state: waiting, seated, canceled or no_show.
  @GET('/v1/owner/waitlist')
  Future<GetV1OwnerWaitlistResponse> ownerWaitlistIndex({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('status') String? status,
  });
}
