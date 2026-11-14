// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_pos_banquets_payment_settings_response.dart';
import '../models/get_v1_owner_pos_banquets_report_response.dart';
import '../models/put_v1_owner_pos_banquets_payment_settings_response.dart';
import '../models/v1_owner_pos_banquets_payment_settings_request_body.dart';

part 'owner_banquet_payments_api.g.dart';

@RestApi()
abstract class OwnerBanquetPaymentsApi {
  factory OwnerBanquetPaymentsApi(Dio dio, {String? baseUrl}) =
      _OwnerBanquetPaymentsApi;

  /// "Tadbirlar" report (Excel).
  ///
  /// As GET /owner/pos/banquets/report: the events on the "Tadbirlar" sheet, the totals on "Jami". Recorded in the.
  /// audit log.
  ///
  /// [branchId] - One branch (a branch-bound caller: their own).
  ///
  /// [from] - First event date (YYYY-MM-DD).
  ///
  /// [to] - Last event date (YYYY-MM-DD).
  @GET('/v1/owner/pos/banquets/export')
  @DioResponseType(ResponseType.stream)
  Stream<String> banquetPaymentsExport({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
  });

  /// Banquet payment rules.
  ///
  /// The schedule template a new event's schedule is planned from (`percent` of the total, `days_before` the event;.
  /// null: at booking; the final row takes the rest), the cancellation rule table (`min_days` → `retained_percent` of.
  /// what was paid) and the reminder days. Defaults when the store saved none (`version` 0).
  @GET('/v1/owner/pos/banquets/payment-settings')
  Future<GetV1OwnerPosBanquetsPaymentSettingsResponse>
  banquetPaymentsSettings();

  /// Save banquet payment rules.
  ///
  /// Any of: `schedule_template` (1–8 rows; the last a `final` row with `percent` null; at most one deposit; percents.
  /// up to 100 in all), `cancellation_rules` (1–10 rows, distinct `min_days`, one of them 0), `reminder_days` (days.
  /// before a due date, 0–60), `overdue_reminders`. `version` (from GET) refuses a stale save. Changes apply to new.
  /// schedules and cancellations from now on. Needs banquets.manage and the `banquet` module; audited.
  @PUT('/v1/owner/pos/banquets/payment-settings')
  Future<PutV1OwnerPosBanquetsPaymentSettingsResponse>
  banquetPaymentsUpdateSettings({
    @Body() V1OwnerPosBanquetsPaymentSettingsRequestBody? body,
  });

  /// "Tadbirlar" report.
  ///
  /// The events dated `from`..`to` (default this month; at most 366 days), one branch with `branch_id`: per event and.
  /// in total — guests, total, paid (advances and bank transfers), outstanding, overdue, retained and refunded parts of.
  /// cancelled events, bank transfers still to give back. The customer's phone is masked without banquets.manage.
  ///
  /// [branchId] - One branch (a branch-bound caller: their own).
  ///
  /// [from] - First event date (YYYY-MM-DD).
  ///
  /// [to] - Last event date (YYYY-MM-DD).
  @GET('/v1/owner/pos/banquets/report')
  Future<GetV1OwnerPosBanquetsReportResponse> banquetPaymentsReport({
    @Query('branch_id') int? branchId,
    @Query('from') String? from,
    @Query('to') String? to,
  });
}
