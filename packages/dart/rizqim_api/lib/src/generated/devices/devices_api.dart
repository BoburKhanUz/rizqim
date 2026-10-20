// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_devices_uuid_push_token_response.dart';
import '../models/delete_v1_devices_uuid_response.dart';
import '../models/get_v1_devices_response.dart';
import '../models/post_v1_devices_response.dart';
import '../models/put_v1_devices_uuid_push_token_response.dart';
import '../models/v1_devices_request_body.dart';
import '../models/v1_devices_uuid_push_token_request_body.dart';

part 'devices_api.g.dart';

@RestApi()
abstract class DevicesApi {
  factory DevicesApi(Dio dio, {String? baseUrl}) = _DevicesApi;

  @GET('/v1/devices')
  Future<GetV1DevicesResponse> deviceIndex();

  /// POST /devices: register this install, or update it when `uuid` is one of the user's devices
  @POST('/v1/devices')
  Future<PostV1DevicesResponse> deviceStore({
    @Body() required V1DevicesRequestBody body,
  });

  @DELETE('/v1/devices/{uuid}')
  Future<DeleteV1DevicesUuidResponse> deviceDestroy({
    @Path('uuid') required String uuid,
  });

  /// PUT /devices/{uuid}/push-token: set the FCM token of one of the user's installs, or clear it with null. The token.
  /// is never shown back (the device only reports has_push_token).
  @PUT('/v1/devices/{uuid}/push-token')
  Future<PutV1DevicesUuidPushTokenResponse> deviceUpdatePushToken({
    @Path('uuid') required String uuid,
    @Body() required V1DevicesUuidPushTokenRequestBody body,
  });

  /// DELETE /devices/{uuid}/push-token: stop pushes to this install (the app was signed out or notifications were turned off)
  @DELETE('/v1/devices/{uuid}/push-token')
  Future<DeleteV1DevicesUuidPushTokenResponse> deviceClearPushToken({
    @Path('uuid') required String uuid,
  });
}
