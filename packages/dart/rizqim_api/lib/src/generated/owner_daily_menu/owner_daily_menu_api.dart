// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_meal_sets_id_response.dart';
import '../models/get_v1_owner_daily_menu_response.dart';
import '../models/get_v1_owner_meal_sets_response.dart';
import '../models/post_v1_owner_daily_menu_copy_response.dart';
import '../models/post_v1_owner_meal_sets_response.dart';
import '../models/put_v1_owner_daily_menu_response.dart';
import '../models/put_v1_owner_daily_menu_rotation_response.dart';
import '../models/put_v1_owner_meal_sets_id_response.dart';
import '../models/v1_owner_daily_menu_copy_request_body.dart';
import '../models/v1_owner_daily_menu_request_body.dart';
import '../models/v1_owner_daily_menu_rotation_request_body.dart';

part 'owner_daily_menu_api.g.dart';

@RestApi()
abstract class OwnerDailyMenuApi {
  factory OwnerDailyMenuApi(Dio dio, {String? baseUrl}) = _OwnerDailyMenuApi;

  /// A branch's menu for a day.
  ///
  /// P6-15, module `daily_menu`: the dishes on the branch's menu for `date` (YYYY-MM-DD, default today's business.
  /// day), `set` false when none was made for that day (then every dish is on sale), and every active dish of the store.
  /// with `daily_rotation` (on THIS branch it is on sale only while on the day's menu) and `on_menu`. `previous_date` is the latest.
  /// earlier day with a menu, for "kechagisini nusxalash". menu.view or menu.manage.
  ///
  /// [branchId] - Required unless the caller is bound to a branch.
  ///
  /// [date] - Business day, YYYY-MM-DD (default: the branch's today).
  @GET('/v1/owner/daily-menu')
  Future<GetV1OwnerDailyMenuResponse> dailyMenuShow({
    @Query('branch_id') int? branchId,
    @Query('date') String? date,
  });

  /// Set a branch's menu for a day.
  ///
  /// P6-15, module `daily_menu`: exactly these dishes (`product_ids`, in the order given; an empty list is a menu with.
  /// nothing on it). Every dish on a menu becomes a rotating one. `date` is today's business day up to 30 days ahead.
  /// menu.manage, or stoplist.manage for the manager who runs the day. Audited (`menu.daily_menu_saved`); the menus are told when it is today's.
  @PUT('/v1/owner/daily-menu')
  Future<PutV1OwnerDailyMenuResponse> dailyMenuSave({
    @Body() required V1OwnerDailyMenuRequestBody body,
  });

  /// Copy a day's menu.
  ///
  /// P6-15, module `daily_menu`: the dishes of `from_date` (default: the latest earlier day with a menu) become the.
  /// menu of `date` (default today); a menu already there is replaced. Dishes deleted or switched off since are left.
  /// out. 422 when there is nothing to copy.
  @POST('/v1/owner/daily-menu/copy')
  Future<PostV1OwnerDailyMenuCopyResponse> dailyMenuCopy({
    @Body() V1OwnerDailyMenuCopyRequestBody? body,
  });

  /// Which dishes rotate on a branch.
  ///
  /// P6-15, module `daily_menu`, per branch like the menu: exactly these dishes (`product_ids`) are sold on the branch.
  /// only while on the day's menu; the others are on sale every day (bread, tea). Dishes put on a menu become rotating.
  /// on that branch by themselves; this is how an owner makes one permanent again. Other branches are never touched.
  /// menu.manage (any branch), or stoplist.manage for the manager of that branch only. Audited (`menu.daily_rotation_saved`).
  @PUT('/v1/owner/daily-menu/rotation')
  Future<PutV1OwnerDailyMenuRotationResponse> dailyMenuRotation({
    @Body() required V1OwnerDailyMenuRotationRequestBody body,
  });

  /// Kompleks list.
  ///
  /// P6-15, module `combo_meal`: the store's complexes. A kompleks is a normal menu product sold at its own price as one.
  /// line; here are its parts (dishes the kitchen prepares for it). menu.view or menu.manage.
  @GET('/v1/owner/meal-sets')
  Future<GetV1OwnerMealSetsResponse> mealSetIndex();

  /// Make a product a kompleks.
  ///
  /// `product_id` is a product of the store (its price is the set's price, set in the menu like any dish's) and `parts`.
  /// its dishes: `variant_id` (a dish's variant), `quantity` (default 1) and an optional `label` ("Birinchi", "Salat").
  /// Up to 12 parts; a part cannot be the kompleks itself or another kompleks. Saving a product that already is a.
  /// kompleks replaces its parts. The sold line keeps the parts it had (`set_parts`).
  @POST('/v1/owner/meal-sets')
  Future<PostV1OwnerMealSetsResponse> mealSetStore();

  /// Change a kompleks.
  ///
  /// Replaces the parts and/or switches the kompleks off (`is_active` false: it is sold as a plain product again).
  @PUT('/v1/owner/meal-sets/{id}')
  Future<PutV1OwnerMealSetsIdResponse> mealSetUpdate({
    @Path('id') required int id,
  });

  /// Delete a kompleks.
  ///
  /// The product stays on the menu as a plain dish; lines already sold keep their parts.
  @DELETE('/v1/owner/meal-sets/{id}')
  Future<DeleteV1OwnerMealSetsIdResponse> mealSetDestroy({
    @Path('id') required int id,
  });
}
