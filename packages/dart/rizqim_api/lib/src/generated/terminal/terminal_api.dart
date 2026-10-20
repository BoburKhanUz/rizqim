// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/delete_v1_devices_device_uuid_staff_session_response.dart';
import '../models/get_v1_terminal_response.dart';
import '../models/get_v1_terminal_staff_response.dart';
import '../models/post_v1_devices_device_uuid_staff_session_response.dart';
import '../models/post_v1_devices_pair_response.dart';
import '../models/v1_devices_device_uuid_staff_session_request_body.dart';
import '../models/v1_devices_pair_request_body.dart';

part 'terminal_api.g.dart';

@RestApi()
abstract class TerminalApi {
  factory TerminalApi(Dio dio, {String? baseUrl}) = _TerminalApi;

  /// POST /devices/pair {code, name?, platform?, app_version?} (no auth, rate limited).
  ///
  /// `code` is the 8-character pairing code shown as ABCD-EFGH; the dash is optional and case does not matter.
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

  @GET('/v1/terminal')
  Future<GetV1TerminalResponse> terminalShow();

  /// GET /terminal/staff: who can sign in on this terminal (name, role, staff code), for the PIN pad
  @GET('/v1/terminal/staff')
  Future<GetV1TerminalStaffResponse> terminalStaff();
}
