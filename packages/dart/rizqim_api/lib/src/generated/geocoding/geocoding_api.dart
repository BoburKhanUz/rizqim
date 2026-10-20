// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_geocode_reverse_response.dart';
import '../models/get_v1_geocode_search_response.dart';

part 'geocoding_api.g.dart';

@RestApi()
abstract class GeocodingApi {
  factory GeocodingApi(Dio dio, {String? baseUrl}) = _GeocodingApi;

  /// Address of a map point.
  ///
  /// GET /geocode/reverse?lat=&lng=  ->  {success, data: {address, lat, lng}}. 404 when the point has no address,.
  /// 503 (maybe with Retry-After) when the geocoding provider does not answer: let the customer type the address.
  @GET('/v1/geocode/reverse')
  Future<GetV1GeocodeReverseResponse> geocodeReverse({
    @Query('lat') required num lat,
    @Query('lng') required num lng,
  });

  /// Search addresses.
  ///
  /// GET /geocode/search?q=  ->  {success, data: [{address, lat, lng}, ...]}  (best match first, empty when nothing matches).
  /// 503 (maybe with Retry-After) when the geocoding provider does not answer.
  @GET('/v1/geocode/search')
  Future<GetV1GeocodeSearchResponse> geocodeSearch({
    @Query('q') required String q,
  });
}
