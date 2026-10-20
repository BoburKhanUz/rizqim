// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_owner_analytics_response.dart';
import '../models/period.dart';

part 'owner_analytics_api.g.dart';

@RestApi()
abstract class OwnerAnalyticsApi {
  factory OwnerAnalyticsApi(Dio dio, {String? baseUrl}) = _OwnerAnalyticsApi;

  /// Sales figures of the owner's store (a branch manager's branch) for a period, computed on the server:.
  /// revenue, order counts, average check, by payment method and order type, best sellers, couriers, average.
  /// kitchen and delivery times, and a daily series for charts.
  ///
  /// `period` is today|week|month|year (the last 1/7/30/365 Asia/Tashkent calendar days) or custom with `from` and `to`.
  /// (YYYY-MM-DD, at most 93 days). Orders count by the day they were created; `revenue` is the total of the delivered.
  /// ones. All money is a number in UZS (two decimals at most). Cached for 60 s per store/branch and period.
  @GET('/v1/owner/analytics')
  Future<GetV1OwnerAnalyticsResponse> ownerAnalyticsIndex({
    @Query('period') Period? period,
    @Query('from') DateTime? from,
    @Query('to') DateTime? to,
  });
}
