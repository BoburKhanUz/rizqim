// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_pos_banquets_extras_extra_response.dart';
import '../models/delete_v1_owner_pos_banquets_packages_package_response.dart';
import '../models/get_v1_owner_pos_banquets_extras_response.dart';
import '../models/get_v1_owner_pos_banquets_halls_response.dart';
import '../models/get_v1_owner_pos_banquets_ops_settings_response.dart';
import '../models/get_v1_owner_pos_banquets_packages_response.dart';
import '../models/get_v1_owner_pos_banquets_settings_response.dart';
import '../models/patch_v1_owner_pos_banquets_extras_extra_response.dart';
import '../models/patch_v1_owner_pos_banquets_packages_package_response.dart';
import '../models/post_v1_owner_pos_banquets_extras_response.dart';
import '../models/post_v1_owner_pos_banquets_packages_response.dart';
import '../models/put_v1_owner_pos_banquets_halls_hall_rates_response.dart';
import '../models/put_v1_owner_pos_banquets_ops_settings_response.dart';
import '../models/put_v1_owner_pos_banquets_settings_response.dart';
import '../models/v1_owner_pos_banquets_extras_extra_request_body.dart';
import '../models/v1_owner_pos_banquets_extras_request_body.dart';
import '../models/v1_owner_pos_banquets_halls_hall_rates_request_body.dart';
import '../models/v1_owner_pos_banquets_ops_settings_request_body.dart';
import '../models/v1_owner_pos_banquets_packages_package_request_body.dart';
import '../models/v1_owner_pos_banquets_packages_request_body.dart';
import '../models/v1_owner_pos_banquets_settings_request_body.dart';

part 'owner_banquets_api.g.dart';

@RestApi()
abstract class OwnerBanquetsApi {
  factory OwnerBanquetsApi(Dio dio, {String? baseUrl}) = _OwnerBanquetsApi;

  /// Banquet extras
  @GET('/v1/owner/pos/banquets/extras')
  Future<GetV1OwnerPosBanquetsExtrasResponse> banquetSetupExtras();

  /// Create a banquet extra.
  ///
  /// `pricing`: per_event (price × quantity) or per_person (price × quantity × the billed guests).
  @POST('/v1/owner/pos/banquets/extras')
  Future<PostV1OwnerPosBanquetsExtrasResponse> banquetSetupStoreExtra({
    @Body() required V1OwnerPosBanquetsExtrasRequestBody body,
  });

  /// Delete a banquet extra.
  ///
  /// An extra an event uses is archived (inactive) instead (`data.deleted` false).
  @DELETE('/v1/owner/pos/banquets/extras/{extra}')
  Future<DeleteV1OwnerPosBanquetsExtrasExtraResponse> banquetSetupDestroyExtra({
    @Path('extra') required String extra,
  });

  /// Change a banquet extra
  @PATCH('/v1/owner/pos/banquets/extras/{extra}')
  Future<PatchV1OwnerPosBanquetsExtrasExtraResponse> banquetSetupUpdateExtra({
    @Path('extra') required String extra,
    @Body() V1OwnerPosBanquetsExtrasExtraRequestBody? body,
  });

  /// Halls and their rent per slot.
  ///
  /// [branchId] - One branch (default: every branch of the store).
  @GET('/v1/owner/pos/banquets/halls')
  Future<GetV1OwnerPosBanquetsHallsResponse> banquetSetupHalls({
    @Query('branch_id') int? branchId,
  });

  /// Set a hall's rent per slot.
  ///
  /// `rates`: {morning|day|evening|custom: price or null (no rent)}. Only the slots sent change; audited.
  @PUT('/v1/owner/pos/banquets/halls/{hall}/rates')
  Future<PutV1OwnerPosBanquetsHallsHallRatesResponse>
  banquetSetupUpdateHallRates({
    @Path('hall') required String hall,
    @Body() required V1OwnerPosBanquetsHallsHallRatesRequestBody body,
  });

  /// Banquet operations settings
  @GET('/v1/owner/pos/banquets/ops-settings')
  Future<GetV1OwnerPosBanquetsOpsSettingsResponse> banquetOpsSettingsShow();

  /// Change the banquet operations settings.
  ///
  /// Only the fields sent change. `reserve_percent` 0–50; `osh`: {persons_per_kg 1–20, ingredients: [{key, name,.
  /// unit kg|l|dona, per_kg}] (up to 30), kazans: kg sizes (up to 10), product_ids: the store's osh products};.
  /// `portion_weights`: [{product_id, grams}]; the MXIK codes (17 digits) and package codes of the package, hall rent.
  /// and extras lines of the final sale receipt (accountant to confirm); `contract_template` (up to 20 000.
  /// characters; empty: the default text). `version`: the one read (409 `version_conflict` when it moved). Audited.
  ///
  /// [idempotencyKey] - Optional: a new UUID per action, the same one when retrying it; a repeat returns the first answer.
  @PUT('/v1/owner/pos/banquets/ops-settings')
  Future<PutV1OwnerPosBanquetsOpsSettingsResponse> banquetOpsSettingsUpdate({
    @Body() required V1OwnerPosBanquetsOpsSettingsRequestBody body,
    @Header('Idempotency-Key') String? idempotencyKey,
  });

  /// Banquet packages.
  ///
  /// Every package of the store (archived ones too), with what they include and their menu items.
  @GET('/v1/owner/pos/banquets/packages')
  Future<GetV1OwnerPosBanquetsPackagesResponse> banquetSetupPackages();

  /// Create a banquet package.
  ///
  /// `per_person_price` (so'm), `minimum_guests`, `included` (free text lines), `items`: menu items per person.
  /// (`product_variant_id` or `product_id` of the store, or a free `name`; `quantity_per_person`, `unit`).
  @POST('/v1/owner/pos/banquets/packages')
  Future<PostV1OwnerPosBanquetsPackagesResponse> banquetSetupStorePackage({
    @Body() required V1OwnerPosBanquetsPackagesRequestBody body,
  });

  /// Delete a banquet package.
  ///
  /// A package an event uses is archived (inactive) instead (`data.deleted` false).
  @DELETE('/v1/owner/pos/banquets/packages/{package}')
  Future<DeleteV1OwnerPosBanquetsPackagesPackageResponse>
  banquetSetupDestroyPackage({@Path('package') required String package});

  /// Change a banquet package.
  ///
  /// Only the fields sent change (`items` replaces the menu items). Events with frozen prices keep theirs.
  @PATCH('/v1/owner/pos/banquets/packages/{package}')
  Future<PatchV1OwnerPosBanquetsPackagesPackageResponse>
  banquetSetupUpdatePackage({
    @Path('package') required String package,
    @Body() V1OwnerPosBanquetsPackagesPackageRequestBody? body,
  });

  /// Banquet settings
  @GET('/v1/owner/pos/banquets/settings')
  Future<GetV1OwnerPosBanquetsSettingsResponse> banquetSetupSettings();

  /// Change the banquet settings.
  ///
  /// Only the fields sent change. `slots`: {morning|day|evening: {start, end}} ("HH:MM", an end before the start is.
  /// the next day); `hold_hours` (0: a hold never expires by itself); `guest_warning_thresholds` and the time window.
  /// are warnings, never refusals; the MXIK codes of the advance receipt line and of the cancellation fee (the.
  /// accountant confirms them). Audited.
  @PUT('/v1/owner/pos/banquets/settings')
  Future<PutV1OwnerPosBanquetsSettingsResponse> banquetSetupUpdateSettings({
    @Body() V1OwnerPosBanquetsSettingsRequestBody? body,
  });
}
