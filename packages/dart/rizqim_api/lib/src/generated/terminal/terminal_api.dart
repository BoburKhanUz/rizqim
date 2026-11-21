// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_devices_device_uuid_staff_session_response.dart';
import '../models/delete_v1_terminal_response.dart';
import '../models/get_v1_pos_releases_response.dart';
import '../models/get_v1_terminal_response.dart';
import '../models/get_v1_terminal_staff_response.dart';
import '../models/post_v1_devices_device_uuid_staff_session_response.dart';
import '../models/post_v1_devices_pair_response.dart';
import '../models/post_v1_terminal_sync_key_response.dart';
import '../models/v1_devices_device_uuid_staff_session_request_body.dart';
import '../models/v1_devices_pair_request_body.dart';

part 'terminal_api.g.dart';

@RestApi()
abstract class TerminalApi {
  factory TerminalApi(Dio dio, {String? baseUrl}) = _TerminalApi;

  /// POST /devices/pair {code, name?, platform?, app_version?, app?, pending_operations?} (no auth, rate limited).
  ///
  /// `code` is the 8-character pairing code shown as ABCD-EFGH; the dash is optional and case does not matter. `app`.
  /// (P6 stage B): which POS app pairs (`cashier`, `waiter`, `kds`, `customer_display`); kept when it runs on the.
  /// code's terminal type (the branch's default LAN hub is a cashier till, never a waiter's phone).
  ///
  /// Wave M5 S3: a POS terminal also gets `sync_key` (`key`: base64 of 32 bytes, `kid`: its number), the secret it.
  /// signs its offline queue with. It is given in this answer only: keep it in the device's secure storage beside the.
  /// token, never log it. `null` for a kitchen display or a customer display.
  ///
  /// Wave M5 S5-a: a build below the minimum version the release registry names for its app and platform (`app`,.
  /// `platform` and `app_version` or the `X-Rizqim-App-Version` header must be given) is refused with 426.
  /// `app_outdated` (`current`, `minimum`, `url` of the latest build) before the code is spent. Without a registry.
  /// minimum, or without those details, pairing works as before.
  ///
  /// Review 3 M3: refused only once the minimum applies (past its `minimum_from`, when GET /terminal would say.
  /// update_required), and never when `pending_operations` (optional, the number of operations the till still holds.
  /// to send, orphaned queues included) is above 0: the till pairs, sends them, and shows the red notice instead.
  @POST('/v1/devices/pair')
  Future<PostV1DevicesPairResponse> terminalPair({
    @Body() required V1DevicesPairRequestBody body,
  });

  /// POST /devices/{device}/staff-session {user_id | staff_code, pin}: a staff session token bound to this terminal
  @POST('/v1/devices/{deviceUuid}/staff-session')
  Future<PostV1DevicesDeviceUuidStaffSessionResponse>
  terminalStartStaffSession({
    @Path('deviceUuid') required String deviceUuid,
    @Body() required V1DevicesDeviceUuidStaffSessionRequestBody body,
  });

  /// DELETE /devices/{device}/staff-session: the terminal signs whoever is on it out (screen lock, shift end)
  @DELETE('/v1/devices/{deviceUuid}/staff-session')
  Future<DeleteV1DevicesDeviceUuidStaffSessionResponse>
  terminalEndStaffSession({@Path('deviceUuid') required String deviceUuid});

  /// POS app releases (public).
  ///
  /// Every non-withdrawn release of the four POS apps: `app` (cashier, waiter, kds, customer_display), `platform`.
  /// (android, windows), `version`, `build`, `file_url`, `sha256`, `size_bytes`, `notes` (Uzbek), `released_at`,.
  /// `is_minimum`, `minimum_from` (the day the notice turns red), `is_latest` (the highest version of its app and.
  /// platform). Sorted by app, platform, then newest first. Empty while nothing was published. No auth, cached 5.
  /// minutes, at most 30 requests a minute per address (429 after).
  @GET('/v1/pos/releases')
  Future<GetV1PosReleasesResponse> posReleaseManifestIndex();

  /// GET /terminal: the terminal, its store and branch.
  ///
  /// P2-30: `branches`, the branches whose real-time channel (`private-pos-branch.{id}`) it may join: its own branch,.
  /// or every branch of the store for a terminal paired without one (a store-wide kitchen display subscribes to each).
  ///
  /// Wave M5 S5-a: `app_release`, what the release registry says about this terminal's app and platform: `current`.
  /// (the version it reported, `1.4.0+7`), `latest`, `minimum` (null: none declared), `state` (an AppReleaseState; update_required means below the minimum and past `minimum_from`, 14 days after it was declared.
  /// unless staff chose another day), `url` (download link of the latest build), `notes` (Uzbek), `minimum_from`.
  /// (ISO time, null without a minimum). `null` when the registry holds no release for the terminal's app and.
  /// platform: the app then shows no notice. Only a notice: no state stops a shift, an order, a payment or a sync.
  /// `current` and `state` follow the version this request reports in `X-Rizqim-App-Version` when it is readable.
  /// (the stored one is refreshed at most once a minute), else the stored one.
  @GET('/v1/terminal')
  Future<GetV1TerminalResponse> terminalShow();

  /// Unpair this terminal (P2-33).
  ///
  /// The terminal revokes itself with its own token (`auth:device`), e.g. before the kitchen display forgets its.
  /// pairing: the same as the owner's `POST /owner/devices/{uuid}/revoke` — the device is revoked, its token and every.
  /// staff PIN session on it stop working at once, and the audit log records `device.revoked` (`self_revoked: true`).
  /// Pairing again needs a new code. Calling it again with the old token: 401.
  @DELETE('/v1/terminal')
  Future<DeleteV1TerminalResponse> terminalDestroy();

  /// GET /terminal/staff: who can sign in on this terminal (name, role, staff code), for the PIN pad.
  ///
  /// P2-30: `role` (comma-separated, e.g. `cook,manager,owner` on a kitchen display) lists only those roles; an.
  /// unknown role: 422.
  ///
  /// [role] - Comma-separated roles to list: owner, manager, cashier, waiter, cook.
  @GET('/v1/terminal/staff')
  Future<GetV1TerminalStaffResponse> terminalStaff({
    @Query('role') String? role,
  });

  /// A new queue signing key for this till.
  ///
  /// Wave M5 S3 (signed offline queue): the secret a POS terminal signs its queued operations with.
  /// (`POST /pos/sync`, `operations[].signed`). A till paired before signing existed calls this once when.
  /// `GET /pos/bootstrap` says `offline.signing: true` and it holds no key. Calling it again rotates: a new `key`.
  /// with `kid + 1`; the server keeps the previous key so operations already queued under it still verify (one.
  /// previous key only: a till must not rotate while its queue is not empty). The server refuses a rotation while.
  /// the queue this till last reported is not empty (`POST /pos/sync` `queue`, `GET /pos/sync/status?queue_size=`):.
  /// 409 `queue_not_empty` — send the queue, or report it empty, then ask again. A till that has no key yet gets its.
  /// first one whatever its queue holds. `key` is base64 of 32 bytes and is.
  /// given in this answer only (it cannot be read again, only replaced): keep it in secure storage, never log it.
  /// No Idempotency-Key here on purpose: an answer kept for replay would keep the key. A lost answer is repaired by.
  /// calling again. Only a POS terminal's own token (403 `terminal_required` otherwise); at most 6 a minute.
  @POST('/v1/terminal/sync-key')
  Future<PostV1TerminalSyncKeyResponse> terminalSyncKey();
}
