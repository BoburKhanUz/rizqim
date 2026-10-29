// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_pos_tips_response.dart';
import '../models/post_v1_pos_tips_uuid_corrections_response.dart';
import '../models/v1_pos_tips_uuid_corrections_request_body.dart';

part 'pos_tips_api.g.dart';

@RestApi()
abstract class PosTipsApi {
  factory PosTipsApi(Dio dio, {String? baseUrl}) = _PosTipsApi;

  /// The tips of a shift.
  ///
  /// The tips of `shift_uuid` (default: the terminal's open shift; without one, the branch's current business day),.
  /// with the summary (by method, personal, pool, by person). Someone without tips.manage, pos.close_shift or.
  /// reports.view (a waiter) sees only the tips that go to them. Needs orders.view.
  ///
  /// [shiftUuid] - The shift (default: the terminal's open shift).
  @GET('/v1/pos/tips')
  Future<GetV1PosTipsResponse> posTipsIndex({
    @Query('shift_uuid') String? shiftUuid,
  });

  /// Correct a tip.
  ///
  /// A manager's correction with a `reason`: the tip now gives `amount` (null: as now) to `mode` personal (`staff_id`;.
  /// null: unassigned) or pool. tips.manage, or a manager's `approval_token` for it (else 403 approval_required).
  /// 409 `tips_period_closed` (a closed pool split covers it); 422 `nothing_to_correct`, `staff_not_found`.
  /// Idempotent. Audited (pos.tip_corrected).
  ///
  /// [idempotencyKey] - Required: a new UUID per action, the same one when retrying it (428 or 400 without).
  @POST('/v1/pos/tips/{uuid}/corrections')
  Future<PostV1PosTipsUuidCorrectionsResponse> posTipsCorrect({
    @Path('uuid') required String uuid,
    @Header('Idempotency-Key') required String idempotencyKey,
    @Body() required V1PosTipsUuidCorrectionsRequestBody body,
  });
}
