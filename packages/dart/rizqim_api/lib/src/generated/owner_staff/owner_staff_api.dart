// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_staff_response.dart';
import '../models/patch_v1_owner_staff_id_response.dart';
import '../models/post_v1_owner_staff_response.dart';
import '../models/v1_owner_staff_id_request_body.dart';
import '../models/v1_owner_staff_request_body.dart';

part 'owner_staff_api.g.dart';

@RestApi()
abstract class OwnerStaffApi {
  factory OwnerStaffApi(Dio dio, {String? baseUrl}) = _OwnerStaffApi;

  /// Staff list.
  ///
  /// The staff the caller manages, newest first: for the owner every manager, cashier, waiter, cook and courier;.
  /// for a branch manager the staff below them of their branch and the store-wide ones. `login_phone_enabled`: the.
  /// phone signs in by SMS code; `login_phone_proof`: `sms` (the member proved it: it cannot be replaced or cleared.
  /// here) or `vouched` (written by an owner, manager or super admin), null without a login phone.
  @GET('/v1/owner/staff')
  Future<GetV1OwnerStaffResponse> staffIndex();

  /// Add a staff member.
  ///
  /// `role`: one the caller may give (a manager: cashier, waiter, cook, courier); `branch_id`: a branch of the store.
  /// (a branch manager: their own). `phone` (+998XXXXXXXXX, optional) becomes their login phone for the partner panel.
  /// and the staff apps; one staff account per phone in a store. The response carries the Telegram invite link.
  @POST('/v1/owner/staff')
  Future<PostV1OwnerStaffResponse> staffStore({
    @Body() required V1OwnerStaffRequestBody body,
  });

  /// Edit a staff member.
  ///
  /// `name`, `branch_id`, `phone` (any subset). A new phone becomes their login phone and ends their app sessions.
  /// signed in with the old one; a phone they proved by SMS themselves cannot be replaced or cleared here (422).
  @PATCH('/v1/owner/staff/{id}')
  Future<PatchV1OwnerStaffIdResponse> staffUpdate({
    @Path('id') required int id,
    @Body() V1OwnerStaffIdRequestBody? body,
  });
}
