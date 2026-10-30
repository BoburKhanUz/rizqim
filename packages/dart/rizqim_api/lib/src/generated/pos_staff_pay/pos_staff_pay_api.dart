// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_clock_response.dart';
import '../models/post_v1_pos_clock_in_response.dart';
import '../models/post_v1_pos_clock_out_response.dart';

part 'pos_staff_pay_api.g.dart';

@RestApi()
abstract class PosStaffPayApi {
  factory PosStaffPayApi(Dio dio, {String? baseUrl}) = _PosStaffPayApi;

  /// My work time.
  ///
  /// The caller's open session (null when not clocked in) and the minutes of their closed sessions of the branch's.
  /// business day today.
  @GET('/v1/pos/clock')
  Future<GetV1PosClockResponse> posClockShow();

  /// Clock in.
  ///
  /// From the caller's PIN session on a paired terminal (403 `terminal_required` otherwise). 409.
  /// `already_clocked_in`; a session the person left open on an earlier business day is set aside for a manager.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/clock/in')
  Future<PostV1PosClockInResponse> posClockIn({
    @Header('Idempotency-Key') required String idempotencyKey,
  });

  /// Clock out.
  ///
  /// Ends the caller's open session (409 `not_clocked_in`). A session longer than 24 hours is set aside for a manager.
  /// instead of counted.
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/clock/out')
  Future<PostV1PosClockOutResponse> posClockOut({
    @Header('Idempotency-Key') required String idempotencyKey,
  });
}
