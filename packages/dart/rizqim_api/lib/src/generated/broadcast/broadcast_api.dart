// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/broadcasting_auth_request_body.dart';
import '../models/post_broadcasting_auth_response.dart';

part 'broadcast_api.g.dart';

@RestApi()
abstract class BroadcastApi {
  factory BroadcastApi(Dio dio, {String? baseUrl}) = _BroadcastApi;

  /// Private kanalga obuna imzosi.
  ///
  /// laravel-echo (Pusher protokoli) chaqiradi: Reverb private kanaliga ulanish uchun imzo beradi. Kim qaysi kanalga ulana olishi routes/channels.php da.
  @FormUrlEncoded()
  @POST('/broadcasting/auth')
  Future<PostBroadcastingAuthResponse> broadcastAuthenticate({
    @Body() required BroadcastingAuthRequestBody body,
  });
}
