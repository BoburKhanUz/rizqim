// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_user_response.dart';
import '../models/post_v1_user_profile_response.dart';
import '../models/v1_user_profile_request_body.dart';

part 'fallback_api.g.dart';

@RestApi()
abstract class FallbackApi {
  factory FallbackApi(Dio dio, {String? baseUrl}) = _FallbackApi;

  @GET('/v1/user')
  Future<GetV1UserResponse> getV1User();

  @POST('/v1/user/profile')
  Future<PostV1UserProfileResponse> postV1UserProfile({
    @Body() required V1UserProfileRequestBody body,
  });
}
