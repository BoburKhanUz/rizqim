// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_pos_vouchers_report_response.dart';
import '../models/get_v1_owner_pos_vouchers_response.dart';
import '../models/get_v1_owner_pos_vouchers_uuid_response.dart';
import '../models/post_v1_owner_pos_vouchers_uuid_block_response.dart';
import '../models/post_v1_owner_pos_vouchers_uuid_unblock_response.dart';
import '../models/v1_owner_pos_vouchers_uuid_block_request_body.dart';
import '../models/v1_owner_pos_vouchers_uuid_unblock_request_body.dart';

part 'owner_vouchers_api.g.dart';

@RestApi()
abstract class OwnerVouchersApi {
  factory OwnerVouchersApi(Dio dio, {String? baseUrl}) = _OwnerVouchersApi;

  /// Gift certificates.
  ///
  /// Newest first, paginated (`per_page` up to 100). Filters: `status` (active, used, blocked, expired — `expired`.
  /// includes the active ones past their date), `q` (code, buyer name or phone digits), `branch_id` (where sold),.
  /// `from`/`to` (sold on these dates). Without vouchers.manage the codes are masked (`print` null) and `q` finds a.
  /// certificate by its whole code only.
  ///
  /// [status] - active, used, blocked or expired.
  ///
  /// [q] - Code, buyer name or phone digits.
  ///
  /// [branchId] - Where it was sold.
  ///
  /// [from] - Sold on or after (YYYY-MM-DD).
  ///
  /// [to] - Sold on or before (YYYY-MM-DD).
  ///
  /// [perPage] - Up to 100 (default 30).
  @GET('/v1/owner/pos/vouchers')
  Future<GetV1OwnerPosVouchersResponse> vouchersIndex({
    @Query('status') String? status,
    @Query('q') String? q,
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
    @Query('per_page') int? perPage,
  });

  /// Gift certificates (Excel).
  ///
  /// The filtered list (as GET /owner/pos/vouchers, at most 5 000 rows; buyer phones masked, codes masked without.
  /// vouchers.manage) on the "Sertifikatlar" sheet and the period report on "Hisobot". Each export is recorded in the audit log.
  ///
  /// [status] - active, used, blocked or expired.
  ///
  /// [q] - Code, buyer name or phone digits.
  ///
  /// [branchId] - Where it was sold.
  ///
  /// [from] - First business day (YYYY-MM-DD).
  ///
  /// [to] - Last business day (YYYY-MM-DD).
  @GET('/v1/owner/pos/vouchers/export')
  @DioResponseType(ResponseType.stream)
  Stream<String> vouchersExport({
    @Query('status') String? status,
    @Query('q') String? q,
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// Gift certificate report.
  ///
  /// For business days `from`..`to` (default today; at most 366 days): sold (the advances, by tender), redeemed on.
  /// bills, refunded back onto certificates, the period's net change of the liability; as of today the outstanding.
  /// liability (what certificates can still pay), and what is blocked or expired unused. `branch_id`: one branch.
  ///
  /// [branchId] - One branch (a branch-bound caller: their own).
  ///
  /// [from] - First business day (YYYY-MM-DD).
  ///
  /// [to] - Last business day (YYYY-MM-DD).
  @GET('/v1/owner/pos/vouchers/report')
  Future<GetV1OwnerPosVouchersReportResponse> vouchersReport({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// One gift certificate, with its history.
  ///
  /// Every entry (sale, redemptions with their bills, refunds back onto it, blocks), newest first (up to 200). The.
  /// buyer's phone, the code and the `print` block are shown in full to vouchers.manage only (masked code, no `print`.
  /// for reports.view).
  @GET('/v1/owner/pos/vouchers/{uuid}')
  Future<GetV1OwnerPosVouchersUuidResponse> vouchersShow({
    @Path('uuid') required String uuid,
  });

  /// Block a gift certificate.
  ///
  /// It pays nothing until unblocked (lost, stolen, sold by mistake); `reason` required. 409 `voucher_blocked` when it.
  /// already is. Needs vouchers.manage; audited.
  @POST('/v1/owner/pos/vouchers/{uuid}/block')
  Future<PostV1OwnerPosVouchersUuidBlockResponse> vouchersBlock({
    @Path('uuid') required String uuid,
    @Body() required V1OwnerPosVouchersUuidBlockRequestBody body,
  });

  /// Unblock a gift certificate.
  ///
  /// It pays again what is left on it (optional `reason`). 409 `voucher_not_blocked`. Needs vouchers.manage; audited.
  @POST('/v1/owner/pos/vouchers/{uuid}/unblock')
  Future<PostV1OwnerPosVouchersUuidUnblockResponse> vouchersUnblock({
    @Path('uuid') required String uuid,
    @Body() V1OwnerPosVouchersUuidUnblockRequestBody? body,
  });
}
