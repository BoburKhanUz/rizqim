// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_me_permissions_response.dart';
import '../models/post_v1_auth_logout_all_response.dart';
import '../models/post_v1_auth_logout_response.dart';
import '../models/post_v1_auth_phone_send_code_response.dart';
import '../models/post_v1_auth_phone_verify_response.dart';
import '../models/post_v1_auth_refresh_response.dart';
import '../models/post_v1_auth_telegram_response.dart';
import '../models/post_v1_phone_send_code_response.dart';
import '../models/v1_auth_phone_send_code_request_body.dart';
import '../models/v1_auth_phone_verify_request_body.dart';
import '../models/v1_auth_refresh_request_body.dart';
import '../models/v1_auth_telegram_request_body.dart';
import '../models/v1_phone_send_code_request_body.dart';
import '../models/v1_phone_verify_code_request_body.dart';

part 'auth_api.g.dart';

@RestApi()
abstract class AuthApi {
  factory AuthApi(Dio dio, {String? baseUrl}) = _AuthApi;

  /// POST /auth/logout: ends the session this token belongs to. A device-bound token also ends the device's refresh.
  /// tokens; a Mini App token just stops working.
  @POST('/v1/auth/logout')
  Future<PostV1AuthLogoutResponse> tokenLogout();

  /// POST /auth/logout-all: ends every session of the user on every device (staff PIN sessions included)
  @POST('/v1/auth/logout-all')
  Future<PostV1AuthLogoutAllResponse> tokenLogoutAll();

  @POST('/v1/auth/phone/send-code')
  Future<PostV1AuthPhoneSendCodeResponse> phoneLoginSendCode({
    @Body() required V1AuthPhoneSendCodeRequestBody body,
  });

  @POST('/v1/auth/phone/verify')
  Future<PostV1AuthPhoneVerifyResponse> phoneLoginVerify({
    @Body() required V1AuthPhoneVerifyRequestBody body,
  });

  /// POST /auth/refresh {refresh_token, device_uuid}: spends the refresh token, returns a new access + refresh pair
  @POST('/v1/auth/refresh')
  Future<PostV1AuthRefreshResponse> tokenRefresh({
    @Body() required V1AuthRefreshRequestBody body,
  });

  @POST('/v1/auth/telegram')
  Future<PostV1AuthTelegramResponse> telegramAuthAuthenticate({
    @Body() required V1AuthTelegramRequestBody body,
  });

  @GET('/v1/me/permissions')
  Future<GetV1MePermissionsResponse> permissionShow();

  @POST('/v1/phone/send-code')
  Future<PostV1PhoneSendCodeResponse> phoneVerificationSendCode({
    @Body() required V1PhoneSendCodeRequestBody body,
  });

  @POST('/v1/phone/verify-code')
  Future<dynamic> phoneVerificationVerifyCode({
    @Body() required V1PhoneVerifyCodeRequestBody body,
  });
}
