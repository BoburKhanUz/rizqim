// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_banquets_calendar_response.dart';
import '../models/get_v1_pos_banquets_catalog_response.dart';
import '../models/get_v1_pos_banquets_response.dart';
import '../models/get_v1_pos_banquets_uuid_response.dart';
import '../models/patch_v1_pos_banquets_uuid_response.dart';
import '../models/post_v1_pos_banquets_response.dart';
import '../models/post_v1_pos_banquets_uuid_freeze_response.dart';
import '../models/post_v1_pos_banquets_uuid_unfreeze_response.dart';
import '../models/v1_pos_banquets_request_body.dart';
import '../models/v1_pos_banquets_uuid_freeze_request_body.dart';
import '../models/v1_pos_banquets_uuid_request_body.dart';
import '../models/v1_pos_banquets_uuid_unfreeze_request_body.dart';

part 'pos_banquets_api.g.dart';

@RestApi()
abstract class PosBanquetsApi {
  factory PosBanquetsApi(Dio dio, {String? baseUrl}) = _PosBanquetsApi;

  /// Banquet events (list, search by phone).
  ///
  /// Without `phone`: the events between `from` and `to` (default this month), by start. With `phone` (at least 4.
  /// digits): the customer's events across dates, newest first. Filter by `status`. At most 200.
  ///
  /// [branchId] - One branch (default: the caller's; the whole store for store-wide staff).
  ///
  /// [phone] - Search by the customer phone (digits, at least 4).
  ///
  /// [status] - An App\Domain\Pos\Banquet\BanquetEventStatus value.
  ///
  /// [from] - First day, Y-m-d (store time zone).
  ///
  /// [to] - Last day, Y-m-d, inclusive.
  @GET('/v1/pos/banquets')
  Future<GetV1PosBanquetsResponse> banquetIndex({
    @Query('branch_id') int? branchId,
    @Query('phone') String? phone,
    @Query('status') String? status,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// Hold halls for an event.
  ///
  /// One or several merged halls (`hall_ids`) of the branch on `event_date` in a `slot` (morning, day, evening with.
  /// the store's times; custom with `start_time`/`end_time` "HH:MM", an end before the start is the next day). The.
  /// event starts as a hold until `hold_expires_at` (released by itself unless the deposit is paid, P6-53). Priced.
  /// from the catalogue: package × max(guests_guaranteed, guests_minimum) + the halls' rent for the slot + `extras`.
  /// (a catalogue `extra_id` or a free `name` + `unit_price` + `per_person`; `quantity`). `guests_minimum` defaults to.
  /// the package's minimum. 409 `slot_taken` (`data.conflicts`) when a hall is held then; 409 `hall_has_bookings`.
  /// when a room inside a hall is booked then. Money in so'm; `quote_currency`/`quote_rate` (USD and the day's rate).
  /// only for display.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/banquets')
  Future<PostV1PosBanquetsResponse> banquetStore({
    @Body() required V1PosBanquetsRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Banquet calendar (halls × days × slots).
  ///
  /// The branch's halls (with their rent per slot) and the events between `from` and `to` (the store's dates, `to`.
  /// inclusive, at most 62 days; default this month). An event merging halls is listed under each of them. Cancelled.
  /// events only with `include_cancelled=1`. `slots`: the store's slot times.
  ///
  /// [branchId] - The branch (default: the one the caller works in).
  ///
  /// [from] - First day, Y-m-d (store time zone).
  ///
  /// [to] - Last day, Y-m-d, inclusive.
  ///
  /// [includeCancelled] - Also cancelled events.
  @GET('/v1/pos/banquets/calendar')
  Future<GetV1PosBanquetsCalendarResponse> banquetCalendar({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('include_cancelled') bool? includeCancelled,
  });

  /// Banquet catalogue for the event editor.
  ///
  /// The store's active packages and extras and the branch's halls with their rent per slot, plus the settings.
  /// (slot times, hold hours, warnings).
  ///
  /// [branchId] - The branch (default: the one the caller works in).
  @GET('/v1/pos/banquets/catalog')
  Future<GetV1PosBanquetsCatalogResponse> banquetCatalog({
    @Query('branch_id') int? branchId,
  });

  /// A banquet event
  @GET('/v1/pos/banquets/{uuid}')
  Future<GetV1PosBanquetsUuidResponse> banquetShow({
    @Path('uuid') required String uuid,
  });

  /// Change or move an event.
  ///
  /// Only the fields sent change (a hold or a confirmed event). Moving it (`event_date`, `slot`, `start_time`/.
  /// `end_time`, `hall_ids`) keeps its payments and is checked for conflicts again (409 `slot_taken`,.
  /// `hall_has_bookings`). `extras` replaces the event's extras (a line kept by its `line_uuid` or catalogue.
  /// `extra_id`). On a frozen event the package cannot change (409 `event_frozen`); guests and extras can (frozen.
  /// prices, new lines at today's price). `version`: the one read (409 `stale_version`).
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @PATCH('/v1/pos/banquets/{uuid}')
  Future<PatchV1PosBanquetsUuidResponse> banquetUpdate({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') String? idempotencyKey,
    @Body() V1PosBanquetsUuidRequestBody? body,
  });

  /// Freeze the prices (contract day).
  ///
  /// Stamps the current prices into the event's lines and the package snapshot; later catalogue edits do not change.
  /// the event. 409 `already_frozen`. Optional `version`.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/banquets/{uuid}/freeze')
  Future<PostV1PosBanquetsUuidFreezeResponse> banquetFreeze({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') String? idempotencyKey,
    @Body() V1PosBanquetsUuidFreezeRequestBody? body,
  });

  /// Unfreeze the prices.
  ///
  /// banquets.manage and a `reason` (audited): the event is priced from the catalogue again. 409 `not_frozen`.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @POST('/v1/pos/banquets/{uuid}/unfreeze')
  Future<PostV1PosBanquetsUuidUnfreezeResponse> banquetUnfreeze({
    @Path('uuid') required String uuid,
    @Body() required V1PosBanquetsUuidUnfreezeRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });
}
