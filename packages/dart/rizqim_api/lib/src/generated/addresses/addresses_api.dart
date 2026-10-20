// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_addresses_id_response.dart';
import '../models/get_v1_addresses_response.dart';
import '../models/post_v1_addresses_response.dart';
import '../models/put_v1_addresses_id_response.dart';
import '../models/v1_addresses_id_request_body.dart';
import '../models/v1_addresses_request_body.dart';

part 'addresses_api.g.dart';

@RestApi()
abstract class AddressesApi {
  factory AddressesApi(Dio dio, {String? baseUrl}) = _AddressesApi;

  /// List saved addresses (default first)
  @GET('/v1/addresses')
  Future<GetV1AddressesResponse> addressIndex();

  /// Save an address.
  ///
  /// `is_default: true` makes it the only default address.
  @POST('/v1/addresses')
  Future<PostV1AddressesResponse> addressStore({
    @Body() required V1AddressesRequestBody body,
  });

  /// Update an address
  @PUT('/v1/addresses/{id}')
  Future<PutV1AddressesIdResponse> addressUpdate({
    @Path('id') required String id,
    @Body() V1AddressesIdRequestBody? body,
  });

  /// Delete an address
  @DELETE('/v1/addresses/{id}')
  Future<DeleteV1AddressesIdResponse> addressDestroy({
    @Path('id') required String id,
  });
}
