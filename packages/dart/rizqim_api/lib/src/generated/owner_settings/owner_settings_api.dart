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

  /// Get store settings
  @GET('/v1/owner/settings')
  Future<GetV1OwnerSettingsResponse> ownerSettingsIndex();

  /// Update store settings.
  ///
  /// `payment_settings` is an object (not a list): the delivery fee and switches, working hours and one entry per.
  /// provider. A provider that cannot be enabled yet (uzum, paynet) is a 422 on `payment_settings`.
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
