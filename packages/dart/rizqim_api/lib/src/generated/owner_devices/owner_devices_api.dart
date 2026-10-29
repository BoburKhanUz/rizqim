// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/device_type.dart';
import '../models/get_v1_owner_devices_response.dart';
import '../models/patch_v1_owner_devices_uuid_response.dart';
import '../models/post_v1_owner_devices_pairing_codes_response.dart';
import '../models/post_v1_owner_devices_uuid_revoke_response.dart';
import '../models/v1_owner_devices_pairing_codes_request_body.dart';
import '../models/v1_owner_devices_uuid_request_body.dart';

part 'owner_devices_api.g.dart';

@RestApi()
abstract class OwnerDevicesApi {
  factory OwnerDevicesApi(Dio dio, {String? baseUrl}) = _OwnerDevicesApi;

  @GET('/v1/owner/devices')
  Future<GetV1OwnerDevicesResponse> storeDeviceIndex({
    @Query('type') DeviceType? type,
    @Query('include_revoked') bool? includeRevoked,
  });

  /// POST /owner/devices/pairing-codes {type: pos|kds|display, branch_id?, name?, till_uuid?}: a one-time code for a terminal.
  ///
  /// `code` is 8 characters shown as ABCD-EFGH (digits and capitals without I, L, O, U); it lasts AUTH_PAIRING_CODE_TTL.
  /// minutes and works once. P6-14: a `display` (customer display / "ready numbers" board) is free (never counts toward.
  /// the plan's devices), needs a branch (`branch_id`) with the `customer_display` or `queue_calling` module, and may.
  /// follow one till (`till_uuid`: a POS terminal of that branch; without it the display shows the branch's latest cart).
  @POST('/v1/owner/devices/pairing-codes')
  Future<PostV1OwnerDevicesPairingCodesResponse> storeDeviceCreatePairingCode({
    @Body() required V1OwnerDevicesPairingCodesRequestBody body,
  });

  @PATCH('/v1/owner/devices/{uuid}')
  Future<PatchV1OwnerDevicesUuidResponse> storeDeviceUpdate({
    @Path('uuid') required String uuid,
    @Body() required V1OwnerDevicesUuidRequestBody body,
  });

  @POST('/v1/owner/devices/{uuid}/revoke')
  Future<PostV1OwnerDevicesUuidRevokeResponse> storeDeviceRevoke({
    @Path('uuid') required String uuid,
  });
}
