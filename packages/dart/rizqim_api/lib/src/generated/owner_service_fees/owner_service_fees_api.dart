// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_owner_service_fees_halls_id_response.dart';
import '../models/get_v1_owner_service_fees_response.dart';
import '../models/put_v1_owner_service_fees_response.dart';
import '../models/v1_owner_service_fees_request_body.dart';

part 'owner_service_fees_api.g.dart';

@RestApi()
abstract class OwnerServiceFeesApi {
  factory OwnerServiceFeesApi(Dio dio, {String? baseUrl}) =
      _OwnerServiceFeesApi;

  /// Service charge and place fee settings.
  ///
  /// The store's default (`branch_id` null) and the branch overrides. A channel list null means `["pos_hall"]`;.
  /// Telegram orders never get either fee. P3-12: a row with `hall_id` is that hall's own (it replaces the branch's.
  /// in the hall).
  @GET('/v1/owner/service-fees')
  Future<GetV1OwnerServiceFeesResponse> serviceFeeIndex();

  /// Set the service charge and place fee.
  ///
  /// For the store (`branch_id` null) or one branch. `service_charge_percent` 0-30; `place_fee_mode` per_person.
  /// (× guests) or per_table. Channels default to pos_hall; telegram is not accepted. P3-12: with `hall_id`, the.
  /// hall's own settings (R6: e.g. VIP rooms 15%, a self-service zone 0%); `DELETE /owner/service-fees/halls/{id}`.
  /// removes them. P6-11: a positive service charge needs the branch's `service_charge` module (403 module_disabled);.
  /// a stolovaya branch (or hall) takes none: 422 service_charge_locked.
  @PUT('/v1/owner/service-fees')
  Future<PutV1OwnerServiceFeesResponse> serviceFeeUpdate({
    @Body() required V1OwnerServiceFeesRequestBody body,
  });

  /// Remove a hall's own service charge.
  ///
  /// The hall goes back to the branch's (or the store's) service charge and place fee. settings.manage.
  @DELETE('/v1/owner/service-fees/halls/{id}')
  Future<DeleteV1OwnerServiceFeesHallsIdResponse> hallDestroyServiceFee({
    @Path('id') required String id,
  });
}
