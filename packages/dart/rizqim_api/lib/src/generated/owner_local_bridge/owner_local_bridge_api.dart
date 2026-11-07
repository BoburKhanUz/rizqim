// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_pos_lan_response.dart';
import '../models/post_v1_owner_pos_lan_rotate_response.dart';
import '../models/put_v1_owner_pos_lan_response.dart';
import '../models/v1_owner_pos_lan_request_body.dart';
import '../models/v1_owner_pos_lan_rotate_request_body.dart';

part 'owner_local_bridge_api.g.dart';

@RestApi()
abstract class OwnerLocalBridgeApi {
  factory OwnerLocalBridgeApi(Dio dio, {String? baseUrl}) =
      _OwnerLocalBridgeApi;

  /// The branch's local bridge.
  ///
  /// `hub`: the till that runs the bridge (`chosen`: the owner picked it; otherwise the printing till, else the first.
  /// paired cashier till, else the first paired till), `candidates`: the branch's active tills (`app`: the POS app it.
  /// runs, null when paired before it was recorded; a waiter's phone is never one), `port` (default 18480), `key_id`.
  /// and `rotated_at`.
  ///
  /// [branchId] - The branch (a branch-bound caller: their own).
  @GET('/v1/owner/pos/lan')
  Future<GetV1OwnerPosLanResponse> branchLanShow({
    @Query('branch_id') int? branchId,
  });

  /// Pick the hub or the port.
  ///
  /// `hub_device_id`: an active till (uuid) of the branch, null: back to the default. `port`: 1024–65535. Send only.
  /// what changes. Audited (settings.pos_lan_changed).
  @PUT('/v1/owner/pos/lan')
  Future<PutV1OwnerPosLanResponse> branchLanUpdate({
    @Body() V1OwnerPosLanRequestBody? body,
  });

  /// Rotate the branch's LAN key.
  ///
  /// A new secret (`key_id` + 1): every terminal takes its new keys with its next bootstrap (a sign-in or restart) and.
  /// cannot join the LAN until then. Use it when a device was lost. Audited (pos.lan_key_rotated).
  @POST('/v1/owner/pos/lan/rotate')
  Future<PostV1OwnerPosLanRotateResponse> branchLanRotate({
    @Body() V1OwnerPosLanRotateRequestBody? body,
  });
}
