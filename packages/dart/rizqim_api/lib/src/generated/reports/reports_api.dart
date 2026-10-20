// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/post_v1_reports_export_response.dart';
import '../models/v1_reports_export_request_body.dart';

part 'reports_api.g.dart';

@RestApi()
abstract class ReportsApi {
  factory ReportsApi(Dio dio, {String? baseUrl}) = _ReportsApi;

  /// Send a sales report to the user's Telegram (owner, manager).
  ///
  /// Built by a queue worker and sent as a file to the user's (or the store's) Telegram chat; the response only says.
  /// it was queued. A custom period needs both dates and is at most 93 days.
  @POST('/v1/reports/export')
  Future<PostV1ReportsExportResponse> orderExportReport({
    @Body() required V1ReportsExportRequestBody body,
  });
}
