// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_branches_id_pos_settings_response.dart';
import '../models/put_v1_owner_branches_id_pos_settings_response.dart';
import '../models/v1_owner_branches_id_pos_settings_request_body.dart';

part 'owner_pos_settings_api.g.dart';

@RestApi()
abstract class OwnerPosSettingsApi {
  factory OwnerPosSettingsApi(Dio dio, {String? baseUrl}) =
      _OwnerPosSettingsApi;

  /// The branch's POS settings.
  ///
  /// `business_day_starts_at`: "HH:MM", the local time (`timezone` of the store) the business day starts, 05:00 unless.
  /// changed. `pos_settings`: every key, stored values over the defaults: `mode` counter|table|hybrid,.
  /// `printing_device_id` (uuid of the "chop etuvchi" kassa), `screen_lock_minutes` (0: never), `queue_numbers`,.
  /// `cashier_discount_max_percent` (P3-37: the discount percent a cashier gives alone, "10.00" by default; null or.
  /// "0.00": always a manager's PIN), `alerts` (`open_room_hours`, `void_amount`, `discount_percent`,.
  /// `unaccepted_online_minutes`, `cash_out_amount` — one cash out of the drawer of at least this, "500000.00" by.
  /// default; null: off), `holidays` (YYYY-MM-DD), `booking_slot_minutes` (null: the server default),.
  /// `booking_confirmation` (P3-38: `auto_send` true by default — the guest is sent the confirmation when a booking is.
  /// confirmed; `sms` false by default — SMS costs money; `cancel_policy` the cancellation rule in it, or null).
  @GET('/v1/owner/branches/{id}/pos-settings')
  Future<GetV1OwnerBranchesIdPosSettingsResponse> branchPosSettingsShow({
    @Path('id') required int id,
  });

  /// Change the branch's POS settings.
  ///
  /// Send only what changes. `business_day_starts_at`: "HH:MM" from 00:00 to 11:59 (an early opener may set 04:00).
  /// `pos_settings`: `mode`; `printing_device_id` (uuid of a POS terminal of this branch, or null for none);.
  /// `screen_lock_minutes` 0-240; `queue_numbers`; `cashier_discount_max_percent` 0-100 (2 decimals) or null;.
  /// `alerts` merges key by key (`open_room_hours` 1-72, `void_amount` 0-100000000, `discount_percent` 0-100,.
  /// `unaccepted_online_minutes` 1-120, `cash_out_amount` 0-100000000; null switches that alert off); `holidays` replaces the whole list (up to 400 dates); `booking_slot_minutes` 30-1440 (how long a booking without an end.
  /// time holds its room; null: the server default); `booking_confirmation` merges key by key (`auto_send`, `sms`.
  /// booleans, `cancel_policy` text up to 300 characters or null). An unknown key is 422.
  @PUT('/v1/owner/branches/{id}/pos-settings')
  Future<PutV1OwnerBranchesIdPosSettingsResponse> branchPosSettingsUpdate({
    @Path('id') required int id,
    @Body() V1OwnerBranchesIdPosSettingsRequestBody? body,
  });
}
