// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_settings_response.dart';
import '../models/post_v1_owner_settings_response.dart';
import '../models/post_v1_owner_settings_upload_response.dart';
import '../models/v1_owner_settings_request_body.dart';

part 'owner_settings_api.g.dart';

@RestApi()
abstract class OwnerSettingsApi {
  factory OwnerSettingsApi(Dio dio, {String? baseUrl}) = _OwnerSettingsApi;

  /// Get store settings.
  ///
  /// A provider not offered now is `{enabled: false, available: false, unavailable_reason}`: `not_integrated` (Uzum,.
  /// Paynet) or, P4-32, `switched_off` (the RIZQIM team switched it off for a while). `telegram.group_link`: the bot.
  /// link that routes the store's orders and notices to a Telegram group (null while the bot has no username);.
  /// `group_linked`: whether a group is linked.
  @GET('/v1/owner/settings')
  Future<GetV1OwnerSettingsResponse> ownerSettingsIndex();

  /// Update store settings.
  ///
  /// `payment_settings` is an object (not a list): the delivery fee and switches, working hours and one entry per.
  /// provider. A provider that cannot be enabled yet (uzum, paynet) is a 422 on `payment_settings`. Wave P: with an own.
  /// merchant account, `paylov` takes merchant_id, consumer_key, consumer_secret, username, password, callback_login and.
  /// callback_password (every one of them, or Paylov orders are paid to the platform); none of them is ever sent back.
  /// (has_consumer_secret...).
  ///
  /// Owner decision 1: an own-account credential equal to the platform's own one for that provider (Payme merchant_id /.
  /// secret_key, Click service_id / merchant_id / secret_key, Paylov merchant_id / consumer_key / consumer_secret /.
  /// password / callback_password; never a login alone) is a 422 with `code` and `data.reason` `platform_credential`.
  /// and `data.fields` (the refused field names, never a value); nothing is saved. Identifiers are compared without.
  /// spaces or invisible characters, case-insensitively, a number without its leading zeros. Only a value sent now is.
  /// judged: one already stored and not sent again never blocks a save (App\Services\Payment\PlatformCredentialGuard,.
  /// run by the Store model on every write). After 5 such refusals in 24 hours a save that changes a credential is a.
  /// 429 `platform_credential_limited` (`data.retry_after` seconds) until the window passes; one that changes none is not.
  /// limited. A new merchant_id / service_id / consumer_key with whitespace, a control or invisible character or any of.
  /// & ; = ? # / is a 422 on that field.
  ///
  /// P3-11: optional `tin` (9 digits), `tax_regime` (vat | vat_simplified | turnover) and `fiscal_settings`, as.
  /// PUT /owner/fiscal/settings takes them; left out = kept.
  ///
  /// Wave P (receipts): optional `legal_address` and `complaint_contact` (the seller's details customers see; left out =.
  /// kept, empty = cleared); a change is written to the audit log (`store.seller_details_updated`). `legal_name` is.
  /// not taken here.
  @POST('/v1/owner/settings')
  Future<PostV1OwnerSettingsResponse> ownerSettingsUpdate({
    @Body() required V1OwnerSettingsRequestBody body,
  });

  /// Upload store asset.
  ///
  /// [file] - Maximum file size: 5120 kilobytes.
  @MultiPart()
  @POST('/v1/owner/settings/upload')
  Future<PostV1OwnerSettingsUploadResponse> ownerSettingsUpload({
    @Part(name: 'file') required File file,
  });
}
