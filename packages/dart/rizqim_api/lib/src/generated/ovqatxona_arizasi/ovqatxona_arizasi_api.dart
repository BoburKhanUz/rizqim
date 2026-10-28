// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/post_v1_store_applications_response.dart';
import '../models/v1_store_applications_request_body.dart';

part 'ovqatxona_arizasi_api.g.dart';

@RestApi()
abstract class OvqatxonaArizasiApi {
  factory OvqatxonaArizasiApi(Dio dio, {String? baseUrl}) =
      _OvqatxonaArizasiApi;

  /// Leave an application to join RIZQIM.
  ///
  /// `name` (the place), `format` (restaurant, cafe, fast_food, canteen, coffee_shop, bakery, teahouse, catering,.
  /// other), `source` (website — default — or telegram), `region_id` (GET /locations: an active region) or `city`, `owner_name`, `phone` (+998 XX XXX XX XX, spaces.
  /// and dashes allowed), `tin` (STIR, 9 digits, optional), `comment` (optional, up to 1000). `website` must stay.
  /// empty (a hidden field for bots). The same phone within 24 hours is taken once. Limited to 5 an hour and 20 a day.
  /// per IP address (429); a phone is taken once a day, however many requests name it. Answers 201 without any data about the application.
  @POST('/v1/store-applications')
  Future<PostV1StoreApplicationsResponse> storeApplicationsStore({
    @Body() required V1StoreApplicationsRequestBody body,
  });
}
