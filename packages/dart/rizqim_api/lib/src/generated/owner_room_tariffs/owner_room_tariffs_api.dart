// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_room_tariffs_id_response.dart';
import '../models/get_v1_owner_room_tariffs_response.dart';
import '../models/patch_v1_owner_room_tariffs_id_response.dart';
import '../models/post_v1_owner_room_tariffs_response.dart';
import '../models/put_v1_owner_room_tariffs_calendar_response.dart';
import '../models/v1_owner_room_tariffs_calendar_request_body.dart';
import '../models/v1_owner_room_tariffs_id_request_body.dart';
import '../models/v1_owner_room_tariffs_request_body.dart';

part 'owner_room_tariffs_api.g.dart';

@RestApi()
abstract class OwnerRoomTariffsApi {
  factory OwnerRoomTariffsApi(Dio dio, {String? baseUrl}) =
      _OwnerRoomTariffsApi;

  /// Room tariffs of a branch.
  ///
  /// The tariffs and the branch's calendar (weekend days, ISO 1 = Monday, default Saturday and Sunday; holidays,.
  /// Y-m-d). The most specific tariff wins: the room's own, then its kind in its hall, then its kind in the branch;.
  /// a holiday without its own price is priced as a weekend, a weekend as a weekday. Without a tariff: no room fee.
  ///
  /// [branchId] - The branch; defaults to the caller's.
  @GET('/v1/owner/room-tariffs')
  Future<GetV1OwnerRoomTariffsResponse> roomTariffIndex({
    @Query('branch_id') int? branchId,
  });

  /// Add a room tariff.
  ///
  /// For one place (`dining_table_id`) or a kind of place (`table_kind`: room, tapchan, table; optionally only in.
  /// `hall_id`), a `day_type` (weekday, weekend, holiday) and a `price` (0 is a free room); `valid_from`/`valid_to`.
  /// (business dates, inclusive) limit it in time.
  @POST('/v1/owner/room-tariffs')
  Future<PostV1OwnerRoomTariffsResponse> roomTariffStore({
    @Body() required V1OwnerRoomTariffsRequestBody body,
  });

  /// Set the branch's weekend days and holidays.
  ///
  /// `weekend_days`: ISO weekdays (1 = Monday … 7 = Sunday); `holidays`: dates (Y-m-d), at most 400. P3-26: the.
  /// holidays are the branch's one list, the same as `pos_settings.holidays` (PUT /owner/branches/{id}/pos-settings):.
  /// saving here replaces that list.
  @PUT('/v1/owner/room-tariffs/calendar')
  Future<PutV1OwnerRoomTariffsCalendarResponse> roomTariffCalendarUpdate({
    @Body() required V1OwnerRoomTariffsCalendarRequestBody body,
  });

  /// Delete a room tariff.
  ///
  /// Open orders get the new price at their next change.
  @DELETE('/v1/owner/room-tariffs/{id}')
  Future<DeleteV1OwnerRoomTariffsIdResponse> roomTariffDestroy({
    @Path('id') required String id,
  });

  /// Edit a room tariff
  @PATCH('/v1/owner/room-tariffs/{id}')
  Future<PatchV1OwnerRoomTariffsIdResponse> roomTariffUpdate({
    @Path('id') required String id,
    @Body() V1OwnerRoomTariffsIdRequestBody? body,
  });
}
