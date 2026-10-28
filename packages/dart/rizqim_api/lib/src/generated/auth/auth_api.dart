// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_me_permissions_response.dart';
import '../models/post_v1_auth_logout_all_response.dart';
import '../models/post_v1_auth_logout_response.dart';
import '../models/post_v1_auth_phone_select_account_response.dart';
import '../models/post_v1_auth_phone_send_code_response.dart';
import '../models/post_v1_auth_phone_verify_response.dart';
import '../models/post_v1_auth_refresh_response.dart';
import '../models/post_v1_auth_telegram_response.dart';
import '../models/post_v1_phone_send_code_response.dart';
import '../models/v1_auth_phone_select_account_request_body.dart';
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

  /// Finish a staff sign-in when the phone holds several staff accounts: the `selection_token` the verify call.
  /// answered with (single use, 5 minutes) and the chosen `user_id` from its `accounts`. A wrong, used or expired.
  /// token, or an account that was not offered, is 422 `selection_invalid` (sign in again with a new code).
  @POST('/v1/auth/phone/select-account')
  Future<PostV1AuthPhoneSelectAccountResponse> phoneLoginSelectAccount({
    @Body() required V1AuthPhoneSelectAccountRequestBody body,
  });

  @POST('/v1/auth/phone/send-code')
  Future<PostV1AuthPhoneSendCodeResponse> phoneLoginSendCode({
    @Body() required V1AuthPhoneSendCodeRequestBody body,
  });

  /// Check the SMS code and sign in.
  ///
  /// Without `account` (or with `account: customer`) the customer holding the phone signs in (created when there is.
  /// none). With `account: staff` the store staff account holding it as its login phone does, and a `device` of type.
  /// `web`, `partner_mobile` or `courier` is required: 403 `staff_account_not_found` when there is none; 409.
  /// `account_selection_required` with `selection_token` and `accounts` when the phone holds several (the same person.
  /// in two stores), and the client then calls POST /auth/phone/select-account.
  ///
  /// P4-25: a phone that only a superadmin / RIZQIM team account holds never signs in here, in either mode: 403.
  /// `platform_account` ("Boshqaruv markazidan kiring"). A customer or staff account with the same phone signs in as usual.
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
