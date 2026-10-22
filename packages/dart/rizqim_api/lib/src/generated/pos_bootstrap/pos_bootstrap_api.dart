// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_bootstrap_response.dart';

part 'pos_bootstrap_api.g.dart';

@RestApi()
abstract class PosBootstrapApi {
  factory PosBootstrapApi(Dio dio, {String? baseUrl}) = _PosBootstrapApi;

  /// POS bootstrap.
  ///
  /// A terminal's staff PIN session works its terminal's branch; a normal staff sign-in works the staff member's own.
  /// branch. An owner (or a terminal paired to the whole store) names the branch with `branch_id`; another store's.
  /// branch is 404, another branch than the caller's own 403 (orders.view).
  ///
  /// `data`: `server_time`; `menu_version` (refetch GET /catalog?channel=pos_* when it changes); `store` (id, name,.
  /// timezone); `branch` (id, name, address, phone, timezone, `business_day_starts_at` "HH:MM", `business_date`.
  /// (the business day it is now), `pos_settings`: `mode` counter|table|hybrid, `printing_device_id` (uuid of the.
  /// "chop etuvchi" kassa or null), `screen_lock_minutes` (0: never), `queue_numbers`, `alerts` (`open_room_hours`,.
  /// `void_amount`, `discount_percent`, `unaccepted_online_minutes`; null: off), `holidays` (dates), `booking_slot_minutes` (default length of a booking, null: the server's)); `device` (the.
  /// terminal: uuid, type, name, `is_printing_device`; null outside a terminal session); `user` (id, name, role,.
  /// permissions); `stations` (active kitchen stations); `station_routes` (dish to station: by product and by.
  /// category; a product route beats its category's); `printers` (active ones this terminal can use: LAN and.
  /// Bluetooth printers, and the USB or built-in printers attached to this very terminal); `staff` (id, name, role,.
  /// photo_url: to pick a waiter). `halls`, `tables` and `shift` are filled by the hall and shift modules (empty.
  /// list, empty list and null until then).
  ///
  /// [branchId] - The branch, for a caller who is not tied to one (the owner, a store-wide terminal).
  @GET('/v1/pos/bootstrap')
  Future<GetV1PosBootstrapResponse> posBootstrapShow({
    @Query('branch_id') int? branchId,
  });
}
