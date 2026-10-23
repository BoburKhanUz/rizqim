// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/channel4.dart';
import '../models/get_v1_pos_bootstrap_response.dart';
import '../models/get_v1_pos_catalog_response.dart';

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
  /// photo_url: to pick a waiter); `halls` (open halls and zones), `tables` (their active places; `layout` in grid.
  /// cells of the floor plan, 40 x 24), `shift` (the shift open on this terminal; null outside a terminal session or.
  /// with none open); P3-31: `offline`, what the till keeps for the limited mode (fees per hall and channel, today's room.
  /// fee and minimum spend per place, the choy-non items, its local number prefix and next number, the limits);.
  /// P3-26: `quick_items`, the one-tap buttons (products marked `quick_add` in the menu, then the.
  /// branch's per-guest "choy-non" items: `source` quick_add | auto_guest, `variant_uuid` to add as a line; prices and.
  /// availability from the POS catalog).
  ///
  /// [branchId] - The branch, for a caller who is not tied to one (the owner, a store-wide terminal).
  @GET('/v1/pos/bootstrap')
  Future<GetV1PosBootstrapResponse> posBootstrapShow({
    @Query('branch_id') int? branchId,
  });

  /// POS menu of a branch.
  ///
  /// P3-26: the typed form of GET /catalog?channel=pos_hall|pos_takeaway (same answer). The branch's store menu on.
  /// the POS `channel` (default pos_hall) with its prices and hidden dishes applied, each variant's unit, step and.
  /// minimum, the modifiers, and what is unavailable on the branch now (`is_available`, `unavailable_reason`:.
  /// stopped | sold_out; `availability`: stop-list and kazan batches); each product's `station_id` (kitchen routing),.
  /// `batch_tracked` (sold from kazan batches) and `quick_add` (a one-tap button). Refetch when the bootstrap's.
  /// `menu_version` changes or on menu.availability_changed. Needs menu.view; `branch_id` for an owner.
  ///
  /// [storeId] - Optional: the caller's own store (id or uuid); another one is 404.
  ///
  /// [channel] - pos_hall (default) or pos_takeaway.
  ///
  /// [branchId] - The branch, for a caller who is not tied to one (the owner, a store-wide terminal).
  @GET('/v1/pos/catalog')
  Future<GetV1PosCatalogResponse> posCatalogIndex({
    @Query('store_id') String? storeId,
    @Query('channel') Channel4? channel,
    @Query('branch_id') int? branchId,
  });
}
